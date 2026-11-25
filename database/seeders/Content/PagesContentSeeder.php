<?php

declare(strict_types=1);

namespace Database\Seeders\Content;

use Illuminate\Database\Seeder;
use Uptms\Media\Models\Media;
use Uptms\Pages\Actions\PublishPageAction;
use Uptms\Pages\Actions\SavePageContentAction;
use Uptms\Pages\Domain\Enums\PageTemplate;
use Uptms\Pages\Models\Page;
use Uptms\Publishing\Domain\Enums\PublishStatus;

/**
 * The Spravato landing page at / (Figma "Spravato"), the UPTMS TMS and
 * Contact pages (Figma Version C, node 25:2; not rendered by site/ yet),
 * plus the Privacy policy (no design; one Legal-template rich
 * text block), built from the block library and published. `{phone}`,
 * `{email}` and `tel:clinic` are replaced by the website with the details
 * from Clinic settings, so a new number needs no page edits. Links the
 * design leaves undefined point at the matching page or section.
 */
class PagesContentSeeder extends Seeder
{
    private const array CALL = ['label' => 'Call {phone}', 'href' => 'tel:clinic'];

    private const array SELF_CHECK = ['label' => 'Request a Free Consultation', 'href' => '/contact'];

    public function __construct(
        private readonly SavePageContentAction $save,
        private readonly PublishPageAction $publish,
    ) {}

    public function run(): void
    {
        $this->page('Spravato', 'home', PageTemplate::Landing, $this->home(), home: true);
        $this->page('TMS therapy', 'tms', PageTemplate::Landing, $this->tms());
        $this->page('Contact', 'contact', PageTemplate::Contact, $this->contact());
        $this->page('Privacy policy', 'privacy', PageTemplate::Legal, $this->privacy());
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(string $title, string $slug, PageTemplate $template, array $blocks, bool $home = false): void
    {
        $path = $home ? '/' : '/'.$slug;

        if (Page::withTrashed()->where('path', $path)->exists()) {
            return;
        }

        $page = new Page(['title' => $title]);
        $page->forceFill([
            'slug' => $slug,
            'path' => $path,
            'template' => $template,
            'status' => PublishStatus::Draft,
            'is_home' => $home,
            'lock_version' => 1,
        ])->save();

        $blocks = array_map(fn (array $block): array => ['type' => $block['type'], 'is_visible' => true, 'data' => $block['data']], $blocks);
        $this->save->execute($page, $blocks, 1, null, checkpoint: true, note: 'Initial content from the Figma design');
        $this->publish->execute($page->refresh());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, data: array<string, mixed>}
     */
    private function block(string $type, array $data): array
    {
        return ['type' => $type, 'data' => $data];
    }

    /** @return array{label: string, href: string} */
    private function link(string $label, string $href): array
    {
        return ['label' => $label, 'href' => $href];
    }

    /**
     * The Spravato landing page (Figma "Spravato", desktop 1440). Every
     * "Request a consultation" button links to #consultation, which opens
     * the consultation dialog (DEVELOPMENT-PLAN.md, Phase 2).
     *
     * @return list<array<string, mixed>>
     */
    private function home(): array
    {
        $consult = $this->link('Request a consultation', '#consultation');
        $guide = $this->document('spravato-medication-guide.pdf');

        return [
            $this->block('landing_hero', [
                'eyebrow' => 'Spravato® treatment · University Place & Tacoma',
                'headline' => 'Could Spravato® be *a next step for you?*',
                'lead' => 'When depression has been hard to treat, it helps to have someone listen.',
                'body' => 'Explore clinician-guided esketamine treatment at University Place TMS Clinic, near Tacoma. Understand your options, ask your questions, and take the next step with a care team.',
                'primary' => $consult,
                'secondary' => self::CALL,
                'image' => SiteMediaSeeder::id('spravato-nasal-spray-use.jpg'),
            ]),
            $this->block('media_text', [
                'rail' => 'Understanding your options',
                'anchor' => 'what-is-spravato',
                'headline' => 'What is Spravato® *(esketamine) treatment?*',
                'body' => "Depression can persist even after trying antidepressants. Spravato is an FDA-approved esketamine nasal spray for adults with treatment-resistant depression — on its own or with an oral antidepressant.\n\nAt our University Place clinic, the first step is a conversation about your previous treatment, current symptoms, health history and goals. A qualified clinician determines whether Spravato could be appropriate for you.",
                'facts' => [
                    ['value' => 'In clinic', 'label' => 'Not a take-home medicine'],
                    ['value' => '2+ hours', 'label' => 'Monitoring after each dose'],
                ],
                'primary' => $consult,
                'link' => $guide === null ? null : $this->link('Read the Medication Guide →', $guide),
                'image' => SiteMediaSeeder::id('spravato-product.jpg'),
                'image_side' => 'left',
                'caption_eyebrow' => 'Prescription nasal spray',
                'caption' => 'Self-administered under a healthcare professional’s supervision.',
            ]),
            $this->block('criteria', [
                'rail' => 'When treatment hasn’t been enough',
                'anchor' => 'eligibility',
                'headline' => 'Is Spravato *right for you?*',
                'intro' => 'You do not need to figure that out alone. A consultation can help you understand treatment-resistant depression and the options worth considering.',
                'primary' => $this->link('Discuss your eligibility', '#consultation'),
                'items' => [
                    ['title' => 'You are an adult living with depression', 'body' => 'Spravato is approved for adults. Your clinician reviews the diagnosis and the symptoms affecting your life.'],
                    ['title' => 'Previous antidepressants haven’t helped enough', 'body' => 'Bring details of medicines you have tried, including how long you took them and how you responded.'],
                    ['title' => 'You want to understand the practical steps', 'body' => 'Discuss health considerations, supervised appointments, a ride home, and insurance requirements before deciding.'],
                ],
                'note' => 'These are starting points for a clinical discussion, not an eligibility test. Benefits and risks are individual.',
            ]),
            $this->block('visit_steps', [
                'rail' => 'What to expect at your appointment',
                'anchor' => 'your-visit',
                'headline' => 'Your visit, *one step at a time.*',
                'intro' => 'Every dose is taken in clinic, under supervision, with time set aside for monitoring before you go home.',
                'steps' => [
                    ['title' => 'Talk with your clinician', 'body' => 'Review your treatment history, medicines, health needs and questions.'],
                    ['title' => 'Take the nasal spray in clinic', 'body' => 'Your healthcare professional explains the device and supervises you as you use it.'],
                    ['title' => 'Stay for observation', 'body' => 'The team monitors you for at least two hours and decides when you are ready to leave.'],
                    ['title' => 'Leave with your arranged ride', 'body' => 'Do not drive or operate machinery until the next day after a restful sleep.'],
                ],
                'note_eyebrow' => 'Plan time for your care',
                'note_body' => 'Allow for check-in, treatment and at least two hours of monitoring. Arrange a ride home for every visit.',
                'note_caption' => 'Representative treatment setting supplied by SPRAVATO®. Not a photograph of the UPTMS clinic.',
                'note_image' => SiteMediaSeeder::id('spravato-treatment-room-01.jpg'),
                'note_cta' => $this->link('Talk through your first visit', '#consultation'),
            ]),
            $this->block('schedule', [
                'rail' => 'Spravato treatment schedule',
                'anchor' => 'schedule',
                'headline' => 'Make a plan that *fits your care.*',
                'lead' => 'Treatment-resistant depression care often starts with more frequent visits. Your prescriber reviews your response and adjusts the schedule over time.',
                'body' => 'Ask how appointments, transportation and follow-up could work alongside your everyday commitments.',
                'rows' => [
                    ['period' => 'Weeks 1–4', 'title' => 'Twice weekly', 'body' => 'Initial treatment phase.'],
                    ['period' => 'Weeks 5–8', 'title' => 'Once weekly', 'body' => 'Review your response with your prescriber.'],
                    ['period' => 'Week 9 onward', 'title' => 'Individualized', 'body' => 'Weekly or every two weeks, as prescribed.'],
                ],
                'note' => 'General TRD schedule. Your clinician determines your dose, frequency and whether treatment should continue.',
            ]),
            $this->block('video_pair', [
                'rail' => 'See what treatment involves',
                'anchor' => 'videos',
                'headline' => 'A clearer picture, *before your visit.*',
                'intro' => 'Two resources from the official SPRAVATO® library. Watch here, then bring your questions to your care team.',
                // Video addresses are added in the admin once the clinic confirms which official videos may be embedded.
                'videos' => [
                    ['title' => 'The treatment journey', 'body' => 'Learn what to expect before, during and after a visit.', 'poster' => SiteMediaSeeder::id('video-treatment-journey.jpg'), 'url' => ''],
                    ['title' => 'How the nasal spray is used', 'body' => 'Official instructions to review with your healthcare professional.', 'poster' => SiteMediaSeeder::id('video-nasal-spray-use.png'), 'url' => ''],
                ],
            ]),
            $this->block('care_team', [
                'rail' => 'Your local care team',
                'anchor' => 'care-team',
                'headline' => 'People who take *time to listen.*',
                'intro' => 'Thoughtful care starts with people who listen. Meet two members of the University Place TMS Clinic team serving patients across Greater Tacoma.',
                'founder_link' => $this->link('Start a conversation →', '#consultation'),
                'member_link' => $this->link('Ask the team a question →', '#consultation'),
            ]),
            $this->block('coverage_location', [
                'rail' => 'Spravato treatment near Tacoma',
                'anchor' => 'coverage',
                'headline' => 'Local care. *Practical answers.*',
                'coverage_eyebrow' => 'Insurance & cost',
                'coverage_title' => 'Questions about coverage?',
                'coverage_body' => 'Coverage depends on your plan, medical-necessity criteria, prior authorization and network status. Ask the clinic about benefits verification and potential out-of-pocket costs before treatment begins.',
                'coverage_items' => ['Benefits verification before you start', 'Prior authorization guidance', 'Clear notes on out-of-pocket costs'],
                'coverage_cta' => $this->link('Ask about coverage & cost', '#consultation'),
                'location_eyebrow' => 'University Place TMS Clinic',
                'location_title' => 'A conversation close to home.',
                'location_body' => 'Find us in University Place, close to Tacoma, Fircrest and Lakewood. Call the clinic for current appointment availability.',
            ]),
            $this->block('faq', [
                'rail' => 'Frequently asked questions',
                'anchor' => 'faq',
                'headline' => 'A little clarity *can help.*',
                'intro' => 'Practical answers about Spravato treatment. Bring any other questions to your consultation.',
                'primary' => $this->link('Ask us · {phone}', 'tel:clinic'),
                'group' => 'spravato',
                'aside_title' => 'Prefer to read first?',
                'aside_body' => 'Official SPRAVATO® documents to review with your provider.',
                'aside_links' => $this->documentLinks(' →'),
            ]),
            $this->block('cta_band', [
                'tone' => 'royal',
                'anchor' => 'next-step',
                'eyebrow' => 'You can start with a question',
                'headline' => 'Let’s talk about *your next step.*',
                'body' => 'Explore Spravato treatment in University Place with a team that takes time to understand your needs.',
                'primary' => $consult,
                'secondary' => self::CALL,
            ]),
        ];
    }

    /**
     * Links to the official documents that have been uploaded, in the
     * Figma order. Empty until the PDFs are supplied.
     *
     * @return list<array{label: string, href: string}>
     */
    private function documentLinks(string $suffix = ''): array
    {
        $links = [];

        foreach (SiteMediaSeeder::DOCUMENTS as $file => [, $title]) {
            $href = $this->document($file);

            if ($href !== null) {
                $links[] = $this->link($title.$suffix, $href);
            }
        }

        return $links;
    }

    /** Site-relative address of an uploaded official document, or null. */
    private function document(string $file): ?string
    {
        $media = Media::query()->where('original_name', $file)->first();
        $path = $media instanceof Media ? parse_url($media->url(), PHP_URL_PATH) : null;

        return is_string($path) ? $path : null;
    }

    /** @return list<array<string, mixed>> */
    private function tms(): array
    {
        return [
            $this->block('hero', [
                'eyebrow' => 'Deep TMS · University Place & Tacoma',
                'headline' => 'Understand your options. *Feel more prepared.*',
                'subheading' => 'Explore how Deep TMS for depression and OCD fits into a wider care plan.',
                'body' => 'Learn about the differences between these treatment pathways, what to ask before your first visit, and how to prepare for a free phone consultation.',
                'primary' => self::SELF_CHECK,
                'aside' => 'image',
                'image' => SiteMediaSeeder::id('tms-clinic-care.jpg'),
                'card_eyebrow' => 'BrainsWay Deep TMS',
                'card_title' => 'A treatment conversation built around you.',
                'card_body' => 'Your provider will explain the potential benefits, risks, and alternatives.',
            ]),
            $this->block('copy_panel', [
                'rail' => 'The basics',
                'anchor' => 'basics',
                'headline' => 'What is TMS therapy?',
                'subheading' => 'Transcranial magnetic stimulation uses magnetic pulses to stimulate areas of the brain involved in mood. TMS is delivered in an outpatient setting while you are awake.',
                'body' => 'University Place TMS Clinic uses BrainsWay Deep TMS. Your evaluation helps determine whether this approach fits your symptoms, treatment history, and medical needs.',
                'cta' => $this->link('Meet the people behind your care →', '#local-team'),
                'panel_title' => 'What to bring to the conversation',
                'panel_items' => [
                    'Your main concerns and treatment goals.',
                    'A list of treatments and medications you have tried.',
                    'Questions about insurance and appointment frequency.',
                    'Relevant health history, including implants or seizures.',
                ],
            ]),
            $this->block('steps', [
                'rail' => 'Your treatment journey',
                'anchor' => 'journey',
                'headline' => 'Know what to expect.',
                'steps_source' => 'inline',
                'steps' => [
                    ['title' => 'Before treatment', 'body' => 'Your provider assesses suitability and reviews health history. Discuss insurance, costs, and the visit schedule before starting.'],
                    ['title' => 'During treatment', 'body' => 'TMS does not require anesthesia. You may feel tapping or discomfort where the stimulation is delivered. Tell your care team how you feel.'],
                    ['title' => 'Over your course of care', 'body' => 'Treatment generally involves repeated visits. Your team will explain the prescribed schedule and review your response over time.'],
                ],
                'panel_title' => 'A balanced conversation about benefits and risks',
                'panel_body' => 'TMS is not right for everyone, and results vary. Common side effects include headache and scalp discomfort. Seizures are a rare serious risk. Your clinician should review medications, seizure history, and metal or implanted devices near your head.',
                'panel_warning' => 'Do not stop prescribed medication without advice from your treating clinician.',
                'panel_links' => [
                    $this->link('Treatment background: National Institute of Mental Health →', 'https://www.nimh.nih.gov/health/topics/brain-stimulation-therapies/brain-stimulation-therapies'),
                    $this->link('Clinic services: UPTMS →', 'https://uptms.com'),
                ],
            ]),
            $this->block('split_cards', [
                'rail' => 'Insurance questions',
                'anchor' => 'local-team',
                'headline' => 'Understand coverage before you commit.',
                'body' => 'Network participation, prior authorization, and out-of-pocket costs depend on your plan. Our insurance section lists the organizations featured by the clinic and questions to help you prepare.',
                'primary' => $this->link('Explore Insurance & Partners', '/#insurance'),
                'team_eyebrow' => 'Your local care team',
                'team_link' => $this->link('Find us & get directions →', '/contact#location'),
            ]),
            $this->block('comparison', [
                'rail' => 'One treatment family · distinct care pathways',
                'anchor' => 'treatment-differences',
                'headline' => 'Depression and OCD need their own treatment conversations.',
                'intro' => 'Both pages describe Deep TMS, but diagnosis, protocol, and insurance criteria should not be treated as interchangeable.',
                'columns' => [
                    ['title' => 'When the focus is depression', 'body' => 'The discussion centers on depressive symptoms, prior antidepressant treatment, therapy history, and day-to-day functioning. Your provider will explain the stimulation approach and how improvement would be assessed.', 'items' => [
                        'What symptoms remain after treatment?',
                        'What medications or therapy have you tried?',
                        'How would your mood and functioning be tracked?',
                    ]],
                    ['title' => 'When the focus is OCD', 'body' => 'The discussion includes obsessions, compulsions, their effect on daily life, and response to OCD treatment. The provider should explain the OCD protocol and how it fits with ongoing care.', 'items' => [
                        'How much time do symptoms take from your day?',
                        'Have you tried OCD-focused therapy such as ERP?',
                        'What would an OCD-specific treatment plan involve?',
                    ]],
                ],
                'callout' => '**Experiencing both?** Tell the clinician. A plan for one condition should not be assumed to treat the other automatically. Ask which symptoms the proposed course targets and how both will be followed.',
                'primary' => $this->link('Discuss My Treatment Questions', '/contact'),
                'link' => $this->link('Request a free consultation →', '/contact'),
            ]),
            $this->block('media_text', [
                'rail' => 'Planning around real life',
                'anchor' => 'planning',
                'headline' => 'Make room for the questions that matter to you.',
                'body' => "Before arranging treatment, consider travel from Tacoma or the surrounding area, work or caregiving commitments, and how repeated appointments would fit your week.\n\nThe clinic’s published guides describe an initial depression course of weekday visits over roughly four to six weeks and an OCD course that may span six to eight weeks. These are planning examples; your clinician must confirm the protocol and visit schedule recommended for you.\n\nSession duration, total visits, and any continuation treatment depend on the prescribed protocol and your response. Ask for those details, plus expected costs, before beginning.",
                'link' => $this->link('View the clinic location and hours →', '/contact#location'),
                'image' => SiteMediaSeeder::id('ocd-understanding.jpg'),
                'image_side' => 'left',
                'caption' => 'Support can begin with a conversation. Illustrative image from the clinic’s website.',
            ]),
            $this->block('faq', [
                'rail' => 'Treatment questions, answered',
                'anchor' => 'faq',
                'headline' => 'Go into your consultation better prepared.',
                'intro' => 'These answers help you plan the conversation. Your clinician can explain what applies to your own care.',
                'primary' => $this->link('Free Phone Consult', 'tel:clinic'),
                'group' => 'tms',
                'footnote' => 'Background:',
                'footnote_links' => [
                    $this->link('NIMH depression', 'https://www.nimh.nih.gov/health/topics/depression'),
                    $this->link('NIMH OCD', 'https://www.nimh.nih.gov/health/topics/obsessive-compulsive-disorder-ocd'),
                    $this->link('FDA OCD device authorization', 'https://www.fda.gov/news-events/press-announcements/fda-permits-marketing-transcranial-magnetic-stimulation-treatment-obsessive-compulsive-disorder'),
                ],
            ]),
            $this->block('cta_band', [
                'tone' => 'royal',
                'eyebrow' => 'Let’s talk.',
                'headline' => 'Your questions are *a good place to start.*',
                'body' => 'Take the free self-assessment or call for a free initial phone consultation.',
                'primary' => $this->link('Request a free consultation →', '/contact'),
                'secondary' => $this->link('Call Us · {phone}', 'tel:clinic'),
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function contact(): array
    {
        return [
            $this->block('consultation', [
                'rail' => 'Free initial phone consultation',
                'anchor' => 'consultation-form',
                'headline' => 'Let’s help you take *the next step.*',
                'subheading' => 'Use the phone number to speak with the clinic during office hours. You can ask general questions without deciding to begin treatment.',
                'items' => [
                    'Learn about TMS and the evaluation process.',
                    'Ask what needs to be checked with your insurer.',
                    'Understand the appointment options.',
                ],
                'call_title' => 'Call the clinic',
                'email_title' => 'Prefer email?',
                'email_note' => 'Please avoid sending sensitive medical details by ordinary email. The clinic can explain how to share records appropriately.',
                'form_title' => 'Request a phone consultation',
                'form_notice' => 'The team calls back within one business day during office hours. To reach the clinic now, call {phone}.',
                'form_help' => 'Fields marked * are required. Clinical evaluations and treatment are separate from the free introductory phone call.',
                'submit_label' => 'Request My Free Phone Consultation',
                'success_body' => 'The team will call you within one business day during office hours. If you need help sooner, call {phone}.',
            ]),
            $this->block('location', [
                'rail' => 'Find us in University Place',
                'anchor' => 'location',
                'headline' => 'Your next step is closer than you think.',
                'show_card' => true,
                'eyebrow' => 'University Place · Washington',
            ]),
            $this->block('cta_band', [
                'tone' => 'royal',
                'eyebrow' => 'Still exploring?',
                'headline' => 'Start with a few *simple questions.*',
                'body' => 'Our free self-assessment helps you prepare for a TMS conversation. It does not diagnose a condition or decide eligibility.',
                'primary' => $this->link('Request a free consultation →', '/contact'),
                'secondary' => $this->link('Learn About TMS', '/tms'),
            ]),
        ];
    }

    /**
     * The website's privacy policy: what the consultation form and self-check
     * collect, how it is protected (docs/ARCHITECTURE.md § 9) and the choices
     * a visitor has. Staff edit it like any page; the clinic's counsel should
     * review it before launch.
     *
     * @return list<array<string, mixed>>
     */
    private function privacy(): array
    {
        return [
            $this->block('rich_text', [
                'rail' => 'Legal',
                'anchor' => 'privacy-policy',
                'headline' => 'Privacy policy',
                'body' => implode("\n\n", [
                    '**Last updated November 8, 2026.** University Place TMS Clinic & General Psychiatry (“the clinic”, “we”) runs this website. This page explains what the site collects, why, who else is involved and the choices you have. It covers this website only. Health information we hold about you as a patient is governed by our Notice of Privacy Practices under HIPAA, which you receive when you become a patient.',

                    '## What this site collects',
                    'You can read every page without telling us who you are. Our hosting provider keeps standard server logs (IP address, browser type, pages requested) to keep the site running and secure; we do not use them to identify you.',
                    'If you send a **consultation request**, we receive what you enter: your first and last name, phone number, email address, insurance, whether you have tried therapy or antidepressants, how and when you would like to be contacted, what you would like to talk about, a referring provider if you name one, and your message. The request also records the page you sent it from, the page on our site you visited just before, the domain of the site that referred you, any campaign tags in the link you followed, and a coarse description of your device such as “Mobile · Safari”.',
                    'The **self-check** is scored in your browser and shown to you there. If you complete the contact step and send it, your answers and scores reach the clinic together with your contact details, in the same way as a consultation request. If you stop before that, nothing you answered leaves your device.',
                    'Please do not send health details by email. Use the form on our [contact page](/contact) or call us.',

                    '## How we use it',
                    'We use what you send to call or email you back, answer your question, check your insurance and keep a record of the request while the clinic follows it up. We do not sell personal information, and we do not use it for advertising.',

                    '## How we protect it',
                    '- Your name, phone, email, message, insurance, treatment history and self-check answers are encrypted in our systems and shown masked in staff lists until a team member opens your request.',
                    '- Only clinic staff with a reason to handle requests can open them, and every time a request is opened is recorded in an access log.',
                    '- Everything you send travels over an encrypted (HTTPS) connection.',
                    '- Alerts about new requests go to the clinic’s own mailbox. The confirmation email you receive carries a reference number and the clinic’s contact details, not what you wrote.',

                    '## How long we keep it',
                    'Consultation requests and self-check submissions are kept in the website’s systems for up to one year after you send them and then deleted automatically. Requests identified as spam are deleted after 30 days. If you become a patient, the details of your care live in your patient record, which follows the clinic’s records policies instead.',

                    '## Who else is involved',
                    '- **Hosting.** The site and its database run with hosting providers who keep them available and secure.',
                    '- **Cloudflare Turnstile** checks that the consultation form was sent by a person, not a script. It runs on the form alone and may see your IP address and browser signals. See the [Cloudflare privacy policy](https://www.cloudflare.com/privacypolicy/).',
                    '- **Google Maps** draws the map of our location. Loading it lets Google receive your IP address and set its own cookies. See the [Google privacy policy](https://policies.google.com/privacy).',
                    '- **Measurement.** We may use Google Tag Manager to understand how the site is used. When it is on, we keep it off the contact and self-check pages, so nothing you type or answer there reaches Google.',
                    '- **Email delivery.** Confirmation emails and staff alerts are sent through an email service on the clinic’s behalf.',
                    'Our fonts are served from our own site, and we use no advertising pixels or social media trackers.',

                    '## Cookies',
                    'The public website sets no cookies of its own. The embedded Google map and the Turnstile check may set theirs; your browser settings let you block them, though the map or the form check may then not work.',

                    '## Your choices',
                    'You can ask us to show you, correct or delete what you sent through this website. Call {phone} or email {email}, and we will confirm who you are before acting. You can also use the site without the form and simply call us.',

                    '## Children',
                    'This website is meant for adults. If you are a parent or guardian asking about care for a minor, please call us rather than entering the minor’s details online.',

                    '## Washington residents',
                    'Washington’s My Health My Data Act gives Washington residents rights over health data collected online. Information you send through this website about your symptoms, treatment history or insurance is collected only to respond to you, is never sold or shared for advertising, is kept only as long as described above, and can be confirmed, corrected or deleted at your request by contacting us. Information we hold about you as a patient is protected health information under HIPAA and is covered by our Notice of Privacy Practices instead.',

                    '## Changes and contact',
                    'We update this page when the website changes; the date at the top is the latest version. Questions about privacy go to University Place TMS Clinic & General Psychiatry, 4007 Bridgeport Way W, Suite B, University Place, WA 98466, by phone at {phone} or by email at {email}.',
                ]),
            ]),
        ];
    }
}
