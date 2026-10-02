import type { ProjectedStage } from './types';

/** Recompose a single illustration with its actual material and tasks. */
export function focusStageBlocks(stage: ProjectedStage) {
    const boardSources = new Set(stage.blocks.filter(block => block.type === 'core.presentation' && block.config.kind === 'response-board').flatMap(block => block.config.sourceBlockIds ?? []));
    const covered = stage.blocks.some(block => block.type === 'core.free-response' && block.runtime?.results && boardSources.has(block.id));
    // The actual board owns the published display; keep the learner's input and own answer intact.
    const visibleBlocks = stage.blocks.filter(block => block.type !== 'core.presentation' || block.config.kind !== 'closing');
    const source = visibleBlocks.length === stage.blocks.length ? stage.blocks : visibleBlocks;
    const blocks = covered ? source.map(block => block.type === 'core.free-response' && block.runtime?.results && boardSources.has(block.id) ? { ...block, runtime: { ...block.runtime, results: undefined } } : block) : source;
    const images = blocks.filter(block => block.type === 'core.image');
    const scene = blocks.find(block => block.type === 'core.presentation' && block.config.kind === 'scene');
    const illustrated = images.length === 1 && blocks.length > 1;
    const journey = blocks.some(block => block.type === 'core.sequence');
    const scenario = blocks.some(block => block.type === 'core.presentation' && block.config.kind === 'discussion');
    const summary = blocks.some(block => block.type === 'core.presentation' && block.config.kind === 'summary') || (illustrated && blocks.some(block => block.type === 'core.text' && block.content?.title && block.config?.presentation === 'list'));
    const sceneHeading = blocks.some(block => block.type === 'core.presentation' && ['scene', 'summary', 'picture-count'].includes(block.config.kind ?? '') && !!block.content?.title);
    const decision = covered || blocks.some(block => block.type === 'core.free-response' && boardSources.has(block.id));
    const choice = !illustrated && blocks.some(block => ['core.poll', 'core.single-choice'].includes(block.type));
    const cover = illustrated && !journey && (scene?.config.scene === 'cover' || (blocks.some(block => block.type === 'core.signals') && blocks.every(block => ['core.text', 'core.image', 'core.signals'].includes(block.type) || (block.type === 'core.presentation' && block.config.kind === 'scene'))));
    // A sequence uses the entire scene; its illustration is a backdrop, never a second narrow column.
    const split = illustrated && !journey && !summary;
    const sceneText = new Set([scene?.content.title, scene?.content.text, scene?.content.subtitle].filter((text): text is string => !!text).map(text => text.trim()));
    const copy = (illustrated ? blocks.filter(block => block !== images[0]) : blocks).map(block => {
        if (block === scene || sceneText.size === 0) return block;
        // The question/body is already printed verbatim by the scene. Preserve its answer widget,
        // all different wording and the immutable source; only avoid printing the exact same line twice.
        const duplicateQuestion = !!block.content?.question && sceneText.has(block.content.question.trim());
        const duplicateBody = block.type === 'core.presentation' && !!block.content?.text && sceneText.has(block.content.text.trim());
        return duplicateQuestion || duplicateBody ? { ...block, content: { ...block.content, ...(duplicateQuestion ? { question: '' } : {}), ...(duplicateBody ? { text: '' } : {}) } } : block;
    });
    return { split, cover, journey, scenario, summary, decision, choice, sceneHeading, sceneVariant: scene?.config.scene,
        sceneLabels: scene?.content, sceneActionLabel: scene?.content.actionLabel,
        mediaFirst: split && !cover && (scene?.config.imageSide ? scene.config.imageSide === 'left' : blocks.some(block => block.type === 'core.free-response')),
        illustration: illustrated ? images[0] : undefined,
        copy: !illustrated && copy.every((block, index) => block === blocks[index]) ? blocks : copy };
}
