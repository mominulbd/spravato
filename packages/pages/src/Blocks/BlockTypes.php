<?php

declare(strict_types=1);

namespace Uptms\Pages\Blocks;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The block library: every section the website's design has, so a page
 * built in the admin can only use layouts the site knows how to render.
 * Adding a block = a definition here + a component in site/
 * (components/blocks).
 */
final class BlockTypes
{
    /** @var array<string, BlockType>|null */
    private ?array $types = null;

    /** @return array<string, BlockType> */
    public function all(): array
    {
        if ($this->types === null) {
            $this->types = [];

            foreach ($this->definitions() as $type) {
                $this->types[$type->key] = $type;
            }
        }

        return $this->types;
    }

    public function get(string $key): BlockType
    {
        return $this->all()[$key] ?? throw new NotFoundHttpException("Unknown block type {$key}.");
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /** @return list<BlockType> */
    private function definitions(): array
    {
        $linkRow = [Field::text('label', 'Label', 80, true), Field::text('href', 'Link', 190, true)];

        return [
            new BlockType('hero', 'Hero', 'layout-template', 'Navy panel with headline, a button and an image or team card.', [
                Field::text('eyebrow', 'Eyebrow', 80),
                Field::headline('headline', 'Headline', 80),
                Field::textarea('subheading', 'Subheading', 200),
                Field::textarea('body', 'Body', 320, help: 'Plain language. Avoid promising results.'),
                Field::link('primary', 'Button')->in('Button'),
                Field::toggle('primary_opens_form', 'Open the qualification form instead of the link', help: 'Shows “Find out if you qualify” in a popup. The link is used when online requests are off.')->in('Button'),
                Field::text('form_title', 'Popup title', 80)->in('Button'),
                Field::textarea('note', 'Small print under the buttons', 200),
                Field::select('aside', 'Right-hand side', ['image' => 'Photo with caption card', 'team' => 'Team card']),
                Field::image('image', 'Photo')->in('Photo'),
                Field::text('card_eyebrow', 'Caption eyebrow', 60)->in('Photo'),
                Field::text('card_title', 'Caption title', 80)->in('Photo'),
                Field::textarea('card_body', 'Caption text', 200)->in('Photo'),
                Field::toggle('show_founder', 'Show the founder chip on the photo')->in('Photo'),
                Field::text('team_eyebrow', 'Team card eyebrow', 60)->in('Team card'),
                Field::headline('team_title', 'Team card title', 80, false)->in('Team card'),
                Field::link('team_link', 'Team card link')->in('Team card'),
                Field::toggle('show_trust_points', 'Show the trust points strip', help: 'Edited in Collections · Trust points.'),
            ], fn (array $data): array => array_filter([
                'founder' => ($data['show_founder'] ?? false) ? ['team', ['founder' => true]] : null,
                'trust_points' => ($data['show_trust_points'] ?? false) ? ['trust-points', []] : null,
                'team' => ($data['aside'] ?? 'image') === 'team' ? ['team', ['page' => 'contact']] : null,
                'form' => ($data['primary_opens_form'] ?? false) ? ['consultation-form', []] : null,
                'insurers' => ($data['primary_opens_form'] ?? false) ? ['insurance', []] : null,
            ])),

            // ── Spravato landing page (Figma "Spravato", desktop 1440) ──────────
            new BlockType('landing_hero', 'Landing hero', 'layout-template', 'Navy panel: eyebrow, headline, two paragraphs, two buttons and a photo.', [
                Field::text('eyebrow', 'Eyebrow', 80),
                Field::headline('headline', 'Headline', 80),
                Field::textarea('lead', 'Lead', 200),
                Field::textarea('body', 'Body', 320, help: 'Plain language. Avoid promising results.'),
                Field::link('primary', 'Main button'),
                Field::link('secondary', 'Second button'),
                Field::image('image', 'Photo'),
            ]),

            new BlockType('criteria', 'Criteria', 'list-checks', 'Sky panel: intro and button beside checklist cards and a note.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('intro', 'Intro', 320),
                Field::link('primary', 'Button'),
                Field::repeater('items', 'Cards', [Field::text('title', 'Title', 80, true), Field::textarea('body', 'Text', 240, true)], 6),
                Field::textarea('note', 'Note under the cards', 240),
            ]),

            new BlockType('visit_steps', 'Visit steps', 'footprints', 'Numbered step cards, then a navy note with a photo and a button.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('intro', 'Intro', 240),
                Field::repeater('steps', 'Steps', [Field::text('title', 'Title', 60, true), Field::textarea('body', 'Text', 240, true)], 6),
                Field::text('note_eyebrow', 'Note eyebrow', 60)->in('Visit note'),
                Field::textarea('note_body', 'Note text', 240)->in('Visit note'),
                Field::textarea('note_caption', 'Photo credit', 200)->in('Visit note'),
                Field::image('note_image', 'Photo')->in('Visit note'),
                Field::link('note_cta', 'Button')->in('Visit note'),
            ]),

            new BlockType('schedule', 'Schedule', 'calendar', 'Navy panel: intro beside a card of schedule rows.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('lead', 'Lead', 320),
                Field::textarea('body', 'Body', 320),
                Field::repeater('rows', 'Rows', [Field::text('period', 'When', 40, true), Field::text('title', 'Title', 60, true), Field::text('body', 'Text', 160)], 6),
                Field::textarea('note', 'Note under the rows', 240),
            ]),

            new BlockType('video_pair', 'Videos', 'play', 'Intro beside the headline, then video cards that load only when played.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('intro', 'Intro', 240),
                Field::repeater('videos', 'Videos', [
                    Field::text('title', 'Title', 80, true),
                    Field::text('body', 'Text', 160),
                    Field::image('poster', 'Cover image'),
                    Field::text('url', 'Video address', 190, help: 'An official video page or file (https://…). Nothing loads until the visitor presses play. Empty shows the cover without a play button.'),
                ], 4),
            ]),

            new BlockType('care_team', 'Care team cards', 'users', 'Photo cards for the people in Collections · Team shown on the Home page.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('intro', 'Intro', 240),
                Field::link('founder_link', 'Founder card link'),
                Field::link('member_link', 'Other cards link'),
            ], fn (array $data): array => ['team' => ['team', ['page' => 'home']]]),

            new BlockType('coverage_location', 'Coverage & location', 'map-pin', 'Insurance card beside the clinic card (address, phone and email from Clinic settings).', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::text('coverage_eyebrow', 'Eyebrow', 60)->in('Coverage card'),
                Field::text('coverage_title', 'Title', 80)->in('Coverage card'),
                Field::textarea('coverage_body', 'Text', 400)->in('Coverage card'),
                Field::list('coverage_items', 'Bullets', 5, 120)->in('Coverage card'),
                Field::link('coverage_cta', 'Button')->in('Coverage card'),
                Field::text('location_eyebrow', 'Eyebrow', 60)->in('Clinic card'),
                Field::text('location_title', 'Title', 80)->in('Clinic card'),
                Field::textarea('location_body', 'Text', 240)->in('Clinic card'),
            ]),

            new BlockType('section_nav', 'Section navigation', 'list', 'Row of pills that jump to sections of the page.', [
                Field::text('lead', 'Lead text', 60),
                Field::source('menu', 'Menu', 'menus', 'Edit the links in Navigation menus.'),
            ], fn (array $data): array => ['items' => ['menus', ['menu' => $data['menu'] ?? 'section-nav']]]),

            new BlockType('copy_panel', 'Copy & checklist', 'file-text', 'Headline and copy beside a soft panel with a checklist.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('subheading', 'Subheading', 320),
                Field::markdown('body', 'Body', 1200),
                Field::link('cta', 'Button')->in('Button'),
                Field::toggle('cta_opens_form', 'Open the qualification form instead of the link', help: 'Shows the same popup as the Home hero. The link is used when online requests are off.')->in('Button'),
                Field::text('form_title', 'Popup title', 80)->in('Button'),
                Field::text('panel_title', 'Panel title', 80)->in('Panel'),
                Field::list('panel_items', 'Checklist', 8, 200)->in('Panel'),
                Field::textarea('panel_note', 'Panel note', 240)->in('Panel'),
            ], fn (array $data): array => array_filter([
                'form' => ($data['cta_opens_form'] ?? false) ? ['consultation-form', []] : null,
                'insurers' => ($data['cta_opens_form'] ?? false) ? ['insurance', []] : null,
            ])),

            new BlockType('consultation', 'Consultation request', 'phone', 'Copy beside the qualification form. Its choices come from the consultation inbox and the insurance partners.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('subheading', 'Subheading', 320),
                Field::list('items', 'What you can ask about', 6, 160),
                Field::text('call_title', 'Call card title', 60)->in('Call card'),
                Field::text('email_title', 'Email note title', 60)->in('Email note'),
                Field::textarea('email_note', 'Email note', 240)->in('Email note'),
                Field::text('form_title', 'Form title', 80)->in('Form'),
                Field::textarea('form_notice', 'Notice above the form', 240)->in('Form'),
                Field::textarea('form_help', 'Help under the form', 240)->in('Form'),
                Field::text('submit_label', 'Submit button', 60)->in('Form'),
                Field::textarea('success_body', 'After sending', 240)->in('Form'),
            ], fn (array $data): array => ['form' => ['consultation-form', []], 'insurers' => ['insurance', []]]),

            new BlockType('media_text', 'Image & text', 'image', 'Photo with a caption chip beside copy, facts and buttons.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::markdown('body', 'Body', 2000),
                Field::repeater('facts', 'Fact tiles', [Field::text('value', 'Value', 40, true), Field::text('label', 'Label', 80, true)], 4),
                Field::textarea('disclaimer', 'Small print', 320),
                Field::link('primary', 'Button'),
                Field::link('link', 'Text link'),
                Field::image('image', 'Photo')->in('Photo'),
                Field::select('image_side', 'Photo side', ['left' => 'Left', 'right' => 'Right'])->in('Photo'),
                Field::text('caption_eyebrow', 'Caption eyebrow', 60)->in('Photo'),
                Field::text('caption', 'Caption', 160)->in('Photo'),
            ]),

            new BlockType('team', 'Care team', 'users', 'Founder card and support team from Collections · Team.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('intro', 'Intro', 240),
                Field::link('primary', 'Founder card button')->in('Button'),
                Field::toggle('primary_opens_form', 'Open the qualification form instead of the link', help: 'Shows the same popup as the Home hero. The link is used when online requests are off.')->in('Button'),
                Field::text('form_title', 'Popup title', 80)->in('Button'),
                Field::link('link', 'Link under the team'),
            ], fn (array $data): array => array_filter([
                'team' => ['team', ['page' => 'home']],
                'form' => ($data['primary_opens_form'] ?? false) ? ['consultation-form', []] : null,
                'insurers' => ($data['primary_opens_form'] ?? false) ? ['insurance', []] : null,
            ])),

            new BlockType('cta_band', 'Call to action band', 'megaphone', 'Coloured band with a headline and buttons.', [
                Field::select('tone', 'Colour', ['sky' => 'Sky', 'royal' => 'Royal blue']),
                Field::anchor(),
                Field::text('eyebrow', 'Lead-in', 80),
                Field::headline('headline', 'Headline', 100),
                Field::textarea('body', 'Body', 320),
                Field::link('primary', 'Primary button'),
                Field::link('secondary', 'Secondary button'),
                Field::link('link', 'Text link'),
            ]),

            new BlockType('insurance', 'Insurance', 'shield', 'Questions to ask, the partner logo wall and the coverage disclaimer.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('intro', 'Intro', 320),
                Field::text('questions_title', 'Questions panel title', 80)->in('Questions'),
                Field::link('questions_cta', 'Questions panel button')->in('Questions'),
                Field::list('questions', 'Questions', 6, 160)->in('Questions'),
                Field::text('logos_title', 'Logo wall title', 80)->in('Logo wall'),
                Field::text('logos_note', 'Logo wall note', 120)->in('Logo wall'),
                Field::textarea('disclaimer', 'Disclaimer', 500)->in('Logo wall'),
                Field::link('disclaimer_link', 'Disclaimer link')->in('Logo wall'),
                Field::text('close_text', 'Closing line', 120),
                Field::link('close_cta', 'Closing button'),
            ], fn (array $data): array => ['partners' => ['insurance', []]]),

            new BlockType('value_grid', 'Value tiles', 'check', 'Grid of tiles with an icon, title and text.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::repeater('tiles', 'Tiles', [
                    Field::select('icon', 'Icon', ['target' => 'Target', 'heart' => 'Heart', 'arrow' => 'Arrow', 'home' => 'Home', 'check' => 'Check', 'sun' => 'Sun']),
                    Field::select('tone', 'Colour', ['white' => 'White', 'navy' => 'Navy']),
                    Field::text('title', 'Title', 60, true),
                    Field::textarea('body', 'Text', 240, true),
                ], 9),
            ]),

            new BlockType('steps', 'Steps', 'corner-up-right', 'Numbered steps, with an optional benefits & risks panel.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::select('steps_source', 'Steps', ['collection' => 'Collections · Journey steps', 'inline' => 'Written here']),
                Field::repeater('steps', 'Steps (when written here)', [Field::text('title', 'Title', 60, true), Field::textarea('body', 'Text', 240, true)], 6),
                Field::link('primary', 'Button'),
                Field::link('link', 'Text link'),
                Field::text('panel_title', 'Panel title', 120)->in('Benefits & risks panel'),
                Field::textarea('panel_body', 'Panel text', 600)->in('Benefits & risks panel'),
                Field::text('panel_warning', 'Bold warning', 160)->in('Benefits & risks panel'),
                Field::repeater('panel_links', 'Panel links', $linkRow, 4)->in('Benefits & risks panel'),
            ], fn (array $data): array => ($data['steps_source'] ?? 'collection') === 'collection' ? ['steps' => ['journey-steps', []]] : []),

            new BlockType('testimonials', 'Patient voices', 'quote', 'Published testimonials with the testimonials disclaimer from Clinic settings.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('intro', 'Intro', 240),
                Field::link('link', 'Link after the disclaimer'),
            ], fn (array $data): array => ['testimonials' => ['testimonials', []]]),

            new BlockType('faq', 'FAQ', 'help-circle', 'Questions from Collections · FAQs beside an intro and a self-check card.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('intro', 'Intro', 240),
                Field::link('primary', 'Button'),
                Field::source('group', 'Questions', 'faqs', 'Which FAQ group this section lists.'),
                Field::text('aside_title', 'Card title', 80)->in('Side card'),
                Field::textarea('aside_body', 'Card text', 200)->in('Side card'),
                Field::link('aside_link', 'Card link')->in('Side card'),
                Field::repeater('aside_links', 'More card links', [Field::text('label', 'Label', 80, true), Field::text('href', 'Link', 190, help: 'Leave empty when a document is chosen.'), Field::document('media_id', 'Or a document')], 4)->in('Side card'),
                Field::text('footnote', 'Footnote', 240),
                Field::repeater('footnote_links', 'Footnote links', $linkRow, 4),
            ], fn (array $data): array => ['faqs' => ['faqs', ['group' => $data['group'] ?? 'home']]]),

            new BlockType('location', 'Location', 'map', 'Map, address, hours and contact details from Clinic settings.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::toggle('show_card', 'Show the info card over the map', true, 'Address, hours, phone and directions. Off shows the map alone.'),
                Field::text('eyebrow', 'Card eyebrow', 60),
            ]),

            new BlockType('split_cards', 'Insurance & local team', 'columns', 'Two cards: a coverage message and the local provider.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('body', 'Body', 320),
                Field::link('primary', 'Button'),
                Field::text('team_eyebrow', 'Team card eyebrow', 60)->in('Team card'),
                Field::link('team_link', 'Team card link')->in('Team card'),
            ], fn (array $data): array => ['founder' => ['team', ['founder' => true]]]),

            new BlockType('comparison', 'Comparison', 'git-compare', 'Side-by-side cards with question lists and a callout.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline'),
                Field::textarea('intro', 'Intro', 320),
                Field::repeater('columns', 'Cards', [
                    Field::text('title', 'Title', 80, true),
                    Field::textarea('body', 'Text', 400, true),
                    Field::list('items', 'Questions', 5, 160),
                ], 3),
                Field::markdown('callout', 'Callout', 400),
                Field::link('primary', 'Button'),
                Field::link('link', 'Text link'),
            ]),

            new BlockType('link_cards', 'Link cards', 'layout-grid', 'Numbered cards that point to other pages.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline', 'Headline', 120, false),
                Field::repeater('cards', 'Cards', [
                    Field::text('title', 'Title', 60, true),
                    Field::textarea('body', 'Text', 200, true),
                    Field::link('link', 'Button'),
                    Field::toggle('opens_form', 'Open the qualification form instead of the link', help: 'Shows the same popup as the Home hero. The link is used when online requests are off.'),
                ], 4),
                Field::text('form_title', 'Popup title', 80),
            ], function (array $data): array {
                /** @var list<array<string, mixed>> $cards */
                $cards = is_array($data['cards'] ?? null) ? $data['cards'] : [];
                $opensForm = array_filter($cards, fn (array $card): bool => (bool) ($card['opens_form'] ?? false)) !== [];

                return array_filter([
                    'form' => $opensForm ? ['consultation-form', []] : null,
                    'insurers' => $opensForm ? ['insurance', []] : null,
                ]);
            }),

            new BlockType('rich_text', 'Rich text', 'align-left', 'A headline and long-form text, for policies and simple pages.', [
                Field::rail(),
                Field::anchor(),
                Field::headline('headline', 'Headline', 120, false),
                Field::markdown('body', 'Body', 20000, true),
            ]),
        ];
    }
}
