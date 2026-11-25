import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { ApiError } from '@shared/api/client';
import { pageRequests, pagesKeys } from './api';
import { fingerprint, splitBlockErrors, toEditorBlocks, toPayload } from './editorState';
import type { BlockData, EditorBlock, PageDetail } from './types';

export type PageMetaState = Omit<PageDetail, 'blocks'>;

export const AUTOSAVE_DELAY = 2500;

/**
 * The page editor's working copy (Figma 07). Saves are queued so two never
 * run at once; each sends the latest blocks with the last lock_version the
 * server returned. Autosave runs AUTOSAVE_DELAY after the last edit and
 * stops on a validation error (until the next edit) or a conflict.
 */
export function useEditor(page: PageDetail) {
    const queryClient = useQueryClient();
    const [blocks, setBlocks] = useState<EditorBlock[]>(() => toEditorBlocks(page.blocks));
    const [meta, setMeta] = useState<PageMetaState>(page);
    const [savedPrint, setSavedPrint] = useState(() => fingerprint(page.blocks));
    const [saving, setSaving] = useState(false);
    const [savedAt, setSavedAt] = useState<number | null>(page.updated_at ? Date.parse(page.updated_at) : null);
    const [saveError, setSaveError] = useState(false);
    const [conflict, setConflict] = useState(false);
    const [errors, setErrors] = useState<Record<string, Record<string, string>>>({});
    const [generalErrors, setGeneralErrors] = useState<string[]>([]);

    const blocksRef = useRef(blocks);
    const lock = useRef(page.lock_version);
    const savedPrintRef = useRef(savedPrint);
    const savedData = useRef<Map<string, BlockData>>(new Map(blocks.map((block) => [block.key, block.data])));
    const failedPrint = useRef<string | null>(null);
    const conflictRef = useRef(false);
    const queue = useRef<Promise<boolean>>(Promise.resolve(true));
    const errorKeys = useRef<string[]>([]);

    useEffect(() => {
        blocksRef.current = blocks;
    }, [blocks]);

    const currentPrint = useMemo(() => fingerprint(toPayload(blocks)), [blocks]);
    const dirty = currentPrint !== savedPrint;

    const applyPage = useCallback(
        (detail: PageDetail, replaceBlocks: boolean) => {
            const { blocks: serverBlocks, ...rest } = detail;
            lock.current = detail.lock_version;
            setMeta(rest);
            queryClient.setQueryData(pagesKeys.detail(detail.id), detail);
            void queryClient.invalidateQueries({ queryKey: ['pages', 'list'] });

            if (replaceBlocks) {
                const next = toEditorBlocks(serverBlocks);
                const print = fingerprint(serverBlocks);
                setBlocks(next);
                blocksRef.current = next;
                savedData.current = new Map(next.map((block) => [block.key, block.data]));
                savedPrintRef.current = print;
                setSavedPrint(print);
                setErrors({});
                setGeneralErrors([]);
            }
        },
        [queryClient],
    );

    const run = useCallback(
        async (checkpoint: boolean): Promise<boolean> => {
            if (conflictRef.current) return false;

            const snapshot = blocksRef.current;
            const payload = toPayload(snapshot);
            const print = fingerprint(payload);

            if (!checkpoint && print === savedPrintRef.current) return true;

            setSaving(true);

            try {
                const response = await pageRequests.saveContent(page.id, { lock_version: lock.current, blocks: payload, checkpoint });
                const ids = new Map(snapshot.map((block, index) => [block.key, response.data.blocks[index]?.id ?? null]));

                setBlocks((current) => current.map((block) => (ids.has(block.key) ? { ...block, id: ids.get(block.key) ?? block.id } : block)));
                savedData.current = new Map(snapshot.map((block) => [block.key, block.data]));
                savedPrintRef.current = print;
                setSavedPrint(print);
                applyPage(response.data, false);
                setSavedAt(Date.now());
                setErrors({});
                errorKeys.current = [];
                setGeneralErrors([]);
                setSaveError(false);
                failedPrint.current = null;

                return true;
            } catch (error) {
                if (error instanceof ApiError && error.code === 'VALIDATION_FAILED') {
                    const split = splitBlockErrors(error.fieldErrors);
                    const byKey: Record<string, Record<string, string>> = {};

                    for (const [index, fields] of Object.entries(split.byBlock)) {
                        const block = snapshot[Number(index)];
                        if (block) byKey[block.key] = fields;
                    }

                    errorKeys.current = snapshot.filter((block) => byKey[block.key]).map((block) => block.key);
                    setErrors(byKey);
                    setGeneralErrors(split.general);
                    failedPrint.current = print;
                } else if (error instanceof ApiError && error.code === 'CONFLICT') {
                    conflictRef.current = true;
                    setConflict(true);
                } else {
                    setSaveError(true);
                }

                return false;
            } finally {
                setSaving(false);
            }
        },
        [page.id, applyPage],
    );

    /** Queue a save; resolves true when everything is saved. */
    const save = useCallback(
        (checkpoint = false) => {
            queue.current = queue.current.then(() => run(checkpoint));

            return queue.current;
        },
        [run],
    );

    useEffect(() => {
        if (!dirty || conflict || currentPrint === failedPrint.current) return;

        const timer = window.setTimeout(() => void save(), AUTOSAVE_DELAY);

        return () => window.clearTimeout(timer);
    }, [currentPrint, dirty, conflict, save]);

    const updateBlock = useCallback((key: string, change: Partial<EditorBlock>) => {
        setBlocks((current) => current.map((block) => (block.key === key ? { ...block, ...change } : block)));
    }, []);

    const revertBlock = useCallback((key: string) => {
        const data = savedData.current.get(key);
        if (data) setBlocks((current) => current.map((block) => (block.key === key ? { ...block, data } : block)));
    }, []);

    return {
        blocks,
        setBlocks,
        meta,
        lockVersion: () => lock.current,
        dirty,
        saving,
        savedAt,
        saveError,
        conflict,
        errors,
        generalErrors,
        save,
        applyPage,
        updateBlock,
        revertBlock,
        hasSavedVersion: (key: string) => savedData.current.has(key),
        /** The first section the last save was refused for. */
        firstErrorKey: () => errorKeys.current[0] ?? null,
    };
}

export type Editor = ReturnType<typeof useEditor>;
