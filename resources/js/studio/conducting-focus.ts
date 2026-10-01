import type { ProjectedStage } from './types';

/** Recompose a single illustration with its actual material and tasks. */
export function focusStageBlocks(stage: ProjectedStage) {
    const boardSources = new Set(stage.blocks.filter(block => block.type === 'core.presentation' && block.config.kind === 'response-board').flatMap(block => block.config.sourceBlockIds ?? []));
    const covered = stage.blocks.some(block => block.type === 'core.free-response' && block.runtime?.results && boardSources.has(block.id));
    // The actual board owns the published display; keep the learner's input and own answer intact.
    const blocks = covered ? stage.blocks.map(block => block.type === 'core.free-response' && block.runtime?.results && boardSources.has(block.id) ? { ...block, runtime: { ...block.runtime, results: undefined } } : block) : stage.blocks;
    const images = blocks.filter(block => block.type === 'core.image');
    const split = images.length === 1 && blocks.length > 1;
    const cover = split && blocks.some(block => block.type === 'core.signals') && blocks.every(block => ['core.text', 'core.image', 'core.signals'].includes(block.type));
    return { split, cover, mediaFirst: !cover && blocks.some(block => block.type === 'core.free-response'), illustration: split ? images[0] : undefined, copy: split ? blocks.filter(block => block !== images[0]) : blocks };
}
