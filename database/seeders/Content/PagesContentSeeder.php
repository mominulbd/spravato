<?php

declare(strict_types=1);

namespace Database\Seeders\Content;

use Illuminate\Database\Seeder;
use Uptms\Pages\Actions\PublishPageAction;
use Uptms\Pages\Actions\SavePageContentAction;
use Uptms\Pages\Domain\Enums\PageTemplate;
use Uptms\Pages\Models\Page;
use Uptms\Publishing\Domain\Enums\PublishStatus;

/**
 * Home, TMS, Contact and Quiz exactly as designed in Figma (Version C,
 * node 25:2), plus the Privacy policy (no design; one Legal-template rich
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
        $this->page('Home', 'home', PageTemplate::Landing, $this->home(), home: true);
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

    /** @return list<array<string, mixed>> */
    private function home(): array
    {
        return [
            $this->block('hero', [
                'eyebrow' => 'Deep TMS therapy in University Place, WA',
                'headline' => 'When depression won’t lift, *explore a new way forward.*',
                'subheading' => 'Medication hasn’t helped enough? You deserve a conversation about what comes next.',
                'body' => 'Explore TMS therapy for depression with the team at University Place TMS Clinic, serving Greater Tacoma. We also offer a dedicated Deep TMS treatment pathway for OCD.',
                'primary' => $this->link('Find Out If You Qualify', '/contact#consultation-form'),
                'primary_opens_form' => true,
                'form_title' => 'Find out if you qualify for TMS',
                'note' => 'Takes about a minute. Prefer a conversation? Your initial phone consultation is free.',
                'aside' => 'image',
                'image' => SiteMediaSeeder::id('tms-clinic-care.jpg'),
                'card_eyebrow' => 'Deep TMS · BrainsWay technology',
                'card_title' => 'Care starts with understanding you.',
                'card_body' => 'Learn about treatment, ask your questions, and decide your next step with a clinician.',
                'show_founder' => true,
                'show_trust_points' => true,
            ]),
            $this->block('section_nav', ['lead' => 'Find what you need', 'menu' => 'section-nav']),
            $this->block('copy_panel', [
                'rail' => 'You don’t have to figure this out alone',
                'anchor' => 'why-tms',
                'headline' => 'Still searching for relief? *Let’s talk about your options.*',
                'subheading' => 'Living with persistent depression can make everyday things feel harder. If your current treatment is not enough, asking about another option is a reasonable next step.',
                'body' => 'At University Place TMS Clinic & General Psychiatry, care includes Deep TMS, medication management, and mental health support for older adults. We begin with your history, your concerns, and the questions that matter to you.',
                'cta' => $this->link('Talk with our team for free →', '/contact'),
                'cta_opens_form' => true,
                'form_title' => 'Find out if you qualify for TMS',
                'panel_title' => 'Does any of this sound familiar?',
                'panel_items' => [
                    'Antidepressants have not provided enough relief.',
                    'Medication side effects are affecting your daily life.',
                    'Depression continues despite treatment.',
                    'You are helping a loved one explore what comes next.',
                ],
                'panel_note' => 'You don’t need to know whether TMS is right for you before you call. That is a conversation we can start together.',
            ]),
            $this->block('media_text', [
                'rail' => 'Understanding Deep TMS',
                'anchor' => 'tms-overview',
                'headline' => 'A different approach to depression treatment.',
                'body' => "TMS stands for transcranial magnetic stimulation. It uses magnetic pulses to stimulate brain areas involved in mood regulation. Treatment takes place while you are awake, without surgery or anesthesia.\n\nOur clinic uses BrainsWay Deep TMS technology. A provider reviews your treatment history and health needs before recommending whether it is an option for you.",
                'facts' => [
                    ['value' => 'Outpatient', 'label' => 'Care without a hospital stay'],
                    ['value' => 'Individualized', 'label' => 'A plan based on your evaluation'],
                ],
                'disclaimer' => 'TMS can have side effects, including headache or scalp discomfort; seizures are a rare serious risk. Your provider will discuss suitability, benefits, and risks.',
                'primary' => $this->link('Request a Free Phone Consult', '/contact'),
                'link' => $this->link('How TMS works →', '/tms'),
                'image' => SiteMediaSeeder::id('tms-treatment-session.jpg'),
                'image_side' => 'left',
                'caption_eyebrow' => 'TMS treatment illustration',
                'caption' => 'Your clinical plan is individualized.',
            ]),
            $this->block('team', [
                'rail' => 'Meet your care team',
                'anchor' => 'care-team',
                'headline' => 'Real people. *A personal approach to care.*',
                'intro' => 'Get to know the team at University Place TMS Clinic before your first conversation.',
                'primary' => $this->link('Talk With Our Care Team', '/contact'),
                'primary_opens_form' => true,
                'form_title' => 'Find out if you qualify for TMS',
                'link' => $this->link('Meet the team on our main clinic website →', 'https://uptms.com'),
            ]),
            $this->block('cta_band', [
                'tone' => 'sky',
                'eyebrow' => 'A simple first step',
                'headline' => 'Questions first. *Decisions together.*',
                'body' => 'Ask about TMS, your insurance, or what an appointment involves. Start with a free phone consultation.',
                'primary' => $this->link('Call Us · {phone}', 'tel:clinic'),
                'link' => $this->link('Request a free consultation →', '/contact'),
            ]),
            $this->block('insurance', [
                'rail' => 'Insurance & access',
                'anchor' => 'insurance',
                'headline' => 'Let’s make sense of your coverage.',
                'intro' => 'Cost should be part of the conversation from the start. Ask our team about your plan, authorization requirements, and potential out-of-pocket costs.',
                'questions_title' => 'What to ask before treatment',
                'questions_cta' => $this->link('Ask About My Insurance', '/contact'),
                'questions' => [
                    'Is the clinic in network for my specific plan?',
                    'Does my plan cover TMS for my diagnosis?',
                    'What records or prior authorization are needed?',
                    'What deductible or copay might apply?',
                ],
                'logos_title' => 'Our partners & coverage resources',
                'logos_note' => 'Plans and programs featured on our current clinic website.',
                'disclaimer_link' => $this->link('View current clinic listings →', 'https://uptms.com'),
                'close_text' => 'Don’t see your plan? We can start with your questions.',
                'close_cta' => $this->link('Call our team →', 'tel:clinic'),
            ]),
            $this->block('value_grid', [
                'rail' => 'Why University Place TMS Clinic?',
                'anchor' => 'why-uptms',
                'headline' => 'Specialized treatment. *Support beyond the session.*',
                'tiles' => [
                    ['icon' => 'target', 'tone' => 'navy', 'title' => 'Deep TMS technology', 'body' => 'Explore treatment using the BrainsWay system with a plan selected through clinical evaluation.'],
                    ['icon' => 'heart', 'tone' => 'white', 'title' => 'A person-first approach', 'body' => 'Bring your questions and your concerns. Your experience helps shape the conversation about care.'],
                    ['icon' => 'arrow', 'tone' => 'white', 'title' => 'Psychiatric care, too', 'body' => 'Our clinic offers psychiatric services and medication management alongside Deep TMS.'],
                    ['icon' => 'home', 'tone' => 'white', 'title' => 'Care close to home', 'body' => 'Visit us in University Place, serving people from the Greater Tacoma community.'],
                    ['icon' => 'check', 'tone' => 'navy', 'title' => 'Coverage conversations', 'body' => 'Ask about your insurance and what needs to be checked before beginning treatment.'],
                    ['icon' => 'sun', 'tone' => 'white', 'title' => 'Support for older adults', 'body' => 'Psychiatric care includes the needs of seniors and people living in supported care settings.'],
                ],
            ]),
            $this->block('steps', [
                'rail' => 'What happens next',
                'anchor' => 'journey',
                'headline' => 'One conversation. *A clearer path forward.*',
                'steps_source' => 'collection',
                'primary' => $this->link('Request My Free Phone Consultation', '/contact'),
                'link' => $this->link('Request a free consultation →', '/contact'),
            ]),
            $this->block('testimonials', [
                'rail' => 'Patient voices',
                'anchor' => 'patient-voices',
                'headline' => 'What our clients say.',
                'intro' => 'Short excerpts published on the clinic’s current website.',
            ]),
            $this->block('faq', [
                'rail' => 'Good questions deserve clear answers',
                'anchor' => 'faq',
                'headline' => 'Before you book.',
                'intro' => 'There is no need to arrive with every answer. We’re here to help you understand the next step.',
                'primary' => $this->link('Ask Us · {phone}', 'tel:clinic'),
                'group' => 'home',
                'aside_title' => 'Prefer to start privately?',
                'aside_body' => 'The free TMS self-check takes a minute. Nothing is sent or saved.',
                'aside_link' => $this->link('Request a free consultation →', '/contact'),
                'footnote' => 'Information supports a conversation with your provider; it is not a diagnosis.',
                'footnote_links' => [
                    $this->link('NIMH depression', 'https://www.nimh.nih.gov/health/topics/depression'),
                    $this->link('NIMH OCD', 'https://www.nimh.nih.gov/health/topics/obsessive-compulsive-disorder-ocd'),
                ],
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
                'eyebrow' => 'You’ve taken the first step by looking.',
                'headline' => 'Let’s find your next step. *Together.*',
                'body' => 'A free phone conversation can help you understand TMS, ask about insurance, and decide whether to schedule an evaluation.',
                'primary' => $this->link('Request a Free Phone Consultation', '/contact'),
                'secondary' => self::CALL,
                'link' => $this->link('Request a free consultation →', '/contact'),
            ]),
        ];
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
