/** A learner assembles stable item IDs; no solution order is exposed here. */
export function shuffledItems(ids: string[], random = Math.random): string[] {
    const result = [...ids];
    for (let index = result.length - 1; index > 0; index--) {
        const other = Math.floor(random() * (index + 1));
        [result[index], result[other]] = [result[other]!, result[index]!];
    }
    return result;
}
export function appendSequenceItem(selected: string[], itemId: string, available: string[]): string[] {
    return available.includes(itemId) && !selected.includes(itemId) ? [...selected, itemId] : selected;
}
export function sequencePositions(selected: string[], revealed?: string[]): ('correct' | 'incorrect' | 'ungraded')[] {
    return selected.map((id, index) => !revealed ? 'ungraded' : revealed[index] === id ? 'correct' : 'incorrect');
}
