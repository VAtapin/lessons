"""Build the complete German lesson plan from a UTF-8 JSON export.

Usage: python scripts/build-neighbor-plan.py INPUT.json OUTPUT.pdf --font-dir FONTS
INPUT may be the documentation object or an object containing a `plan` string.
Requires reportlab; embeds Georgia regular/bold from the supplied font directory.
"""
import argparse
import json
import re
from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak


def build(source, output, font_dir):
    data = json.loads(Path(source).read_text(encoding='utf-8-sig'))
    plan = data.get('plan') or data['content']['de']['plan']
    paragraphs = re.split(r'\n\s*\n', plan.strip())
    title, subtitle = paragraphs.pop(0).splitlines()
    pdfmetrics.registerFont(TTFont('Georgia', str(Path(font_dir) / 'georgia.ttf')))
    pdfmetrics.registerFont(TTFont('GeorgiaBold', str(Path(font_dir) / 'georgiab.ttf')))
    ink, brown, orange = map(colors.HexColor, ['#352B24', '#51331F', '#CC681E'])
    body = ParagraphStyle('Body', fontName='Georgia', fontSize=10.8, leading=15.5,
                          textColor=ink, spaceAfter=11, alignment=TA_LEFT)
    heading = ParagraphStyle('Heading', parent=body, fontName='GeorgiaBold',
                             fontSize=13, leading=18, textColor=brown,
                             spaceBefore=9, spaceAfter=12, keepWithNext=True)
    title_style = ParagraphStyle('Title', parent=heading, fontSize=26, leading=31,
                                spaceBefore=0, spaceAfter=8)
    subtitle_style = ParagraphStyle('Subtitle', parent=body, fontSize=11, leading=16,
                                   textColor=brown, spaceAfter=22)

    def paragraph(text, style):
        return Paragraph(escape(text).replace('\n', '<br/>'), style)

    story = [paragraph(title, title_style), paragraph(subtitle, subtitle_style)]
    for text in paragraphs:
        section = re.match(r'^(\d+)\. ', text)
        if section and section.group(1) in ('4', '7'):
            story.append(PageBreak())
        story.append(paragraph(text, heading if section else body))

    def decorate(canvas, document):
        width, height = A4
        canvas.saveState()
        canvas.setFillColor(colors.HexColor('#FFFCF6'))
        canvas.rect(0, 0, width, height, fill=1, stroke=0)
        canvas.setStrokeColor(orange)
        canvas.setLineWidth(1.5)
        canvas.line(52, height - 38, 90, height - 38)
        canvas.setFont('Georgia', 8)
        canvas.setFillColor(brown)
        canvas.drawString(52, 31, title)
        canvas.drawRightString(width - 52, 31, str(document.page))
        canvas.restoreState()

    Path(output).parent.mkdir(parents=True, exist_ok=True)
    doc = SimpleDocTemplate(str(output), pagesize=A4, leftMargin=52, rightMargin=52,
                           topMargin=55, bottomMargin=53, title=title,
                           author='Lessons', subject=subtitle, pageCompression=1)
    doc.build(story, onFirstPage=decorate, onLaterPages=decorate)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('input')
    parser.add_argument('output')
    parser.add_argument('--font-dir', required=True)
    args = parser.parse_args()
    build(args.input, args.output, args.font_dir)
