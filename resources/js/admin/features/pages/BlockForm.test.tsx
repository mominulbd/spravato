import { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { BlockForm } from './BlockForm';
import type { BlockData, BlockField } from './types';

const fields: BlockField[] = [
    { name: 'headline', type: 'headline', label: 'Headline', required: true, max: 80, help: 'Wrap words in *asterisks*.' },
    { name: 'primary', type: 'link', label: 'Primary button', group: 'Buttons' },
    { name: 'secondary', type: 'link', label: 'Secondary button', group: 'Buttons' },
    { name: 'panel_items', type: 'list', label: 'Checklist', max: 200, max_items: 4 },
    { name: 'tags', type: 'list', label: 'Trust points', max: 60, max_items: 4 },
    { name: 'tone', type: 'select', label: 'Colour', required: true, options: [{ value: 'sky', label: 'Sky' }, { value: 'royal', label: 'Royal blue' }] },
    { name: 'facts', type: 'repeater', label: 'Fact tiles', max_items: 4, fields: [{ name: 'value', type: 'text', label: 'Value', required: true, max: 40 }, { name: 'label', type: 'text', label: 'Label', required: true, max: 80 }] },
    { name: 'show', type: 'toggle', label: 'Show the trust points strip' },
];

function Harness({ initial, errors, onData }: { initial: BlockData; errors?: Record<string, string>; onData?: (data: BlockData) => void }) {
    const [data, setData] = useState(initial);

    return (
        <BlockForm
            fields={fields}
            data={data}
            errors={errors}
            onChange={(next) => {
                setData(next);
                onData?.(next);
            }}
        />
    );
}

const initial: BlockData = {
    headline: 'Questions first. *Decisions together.*',
    primary: { label: 'Call Us', href: 'tel:clinic' },
    secondary: { label: '', href: '' },
    panel_items: ['Antidepressants have not provided enough relief.'],
    tags: ['BrainsWay Deep TMS'],
    tone: 'sky',
    facts: [{ value: 'Outpatient', label: 'Care without a hospital stay' }],
    show: false,
};

describe('BlockForm', () => {
    it('renders every field type from the schema with counters and groups', () => {
        render(<Harness initial={initial} />);

        expect(screen.getByLabelText(/^Headline/)).toHaveValue('Questions first. *Decisions together.*');
        expect(screen.getByText('38 / 80')).toBeInTheDocument();
        expect(screen.getByRole('group', { name: 'Primary button' })).toBeInTheDocument();
        expect(screen.getByRole('group', { name: 'Secondary button' })).toBeInTheDocument();
        expect(screen.getByLabelText('Checklist item 1')).toHaveValue('Antidepressants have not provided enough relief.');
        expect(screen.getByText('BrainsWay Deep TMS')).toBeInTheDocument();
        expect(screen.getByLabelText(/^Colour/)).toHaveValue('sky');
        expect(screen.getByRole('switch', { name: 'Show the trust points strip' })).not.toBeChecked();
        expect(screen.getByLabelText(/^Value/)).toHaveValue('Outpatient');
        expect(screen.getByText(/\{phone\} for the clinic’s number/)).toBeInTheDocument();
    });

    it('edits links, list items and repeater rows into the block data', async () => {
        const user = userEvent.setup();
        let latest: BlockData = initial;
        render(<Harness initial={initial} onData={(data) => (latest = data)} />);

        const secondary = screen.getByRole('group', { name: 'Secondary button' });
        await user.type(secondary.querySelector('input') as HTMLInputElement, 'Take the self-check');
        await user.click(screen.getByRole('button', { name: 'Add item' }));
        await user.type(screen.getByLabelText('Checklist item 2'), 'Side effects');
        await user.click(screen.getByRole('button', { name: 'Move item 2 up' }));
        await user.click(screen.getByRole('button', { name: /Add fact tile/ }));

        expect(latest.secondary).toEqual({ label: 'Take the self-check', href: '' });
        expect(latest.panel_items).toEqual(['Side effects', 'Antidepressants have not provided enough relief.']);
        expect(latest.facts).toHaveLength(2);
        expect((latest.facts as BlockData[])[1]).toEqual({ value: '', label: '' });
    });

    it('shows API errors on the matching field, including nested rows', () => {
        render(
            <Harness
                initial={initial}
                errors={{ headline: 'The headline field is required.', 'primary.href': 'The link format is invalid.', 'facts.0.label': 'The label field is required.' }}
            />,
        );

        expect(screen.getByLabelText(/^Headline/)).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByText('The headline field is required.')).toBeInTheDocument();
        expect(screen.getByText('The link format is invalid.')).toBeInTheDocument();
        expect(screen.getByText('The label field is required.')).toBeInTheDocument();
    });
});
