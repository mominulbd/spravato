<?php

declare(strict_types=1);

namespace Database\Seeders\Content;

use Illuminate\Database\Seeder;
use Uptms\Collections\Actions\SaveCollectionItem;
use Uptms\Collections\Models\CollectionItem;
use Uptms\Collections\Models\ContentItem;
use Uptms\Collections\Models\Faq;
use Uptms\Collections\Models\InsurancePartner;
use Uptms\Collections\Models\TeamMember;
use Uptms\Collections\Models\Testimonial;
use Uptms\Collections\Services\CollectionTypes;

/** Team, FAQs, testimonials, insurance partners, trust points and journey steps — verbatim from Figma (Home, TMS). */
class CollectionsContentSeeder extends Seeder
{
    public function __construct(private readonly CollectionTypes $types) {}

    public function run(): void
    {
        if (TeamMember::query()->exists()) {
            return;
        }

        $this->team();
        $this->faqs();
        $this->testimonials();
        $this->insurance();
        $this->contentItems();
    }

    private function team(): void
    {
        // Joy first: the Contact team card lists her before Ayub (Figma 33:389); the founder card finds Ayub by is_founder.
        $this->add('team', TeamMember::class, [
            'name' => 'Joy Kiongo',
            'role_title' => 'TMS Consultant',
            'bio' => 'Your clinic contact for a conversation about TMS and next steps.',
            'photo_id' => SiteMediaSeeder::id('joy-kiongo.jpg'),
            'show_on' => ['home', 'contact'],
            'schema_type' => 'Person',
        ]);
        $this->add('team', TeamMember::class, [
            'name' => 'Ayub Mwaura',
            'credentials' => 'MSN, PMHNP-BC',
            'role_title' => 'Founder & psychiatric nurse practitioner',
            'bio' => "Ayub is a board-certified psychiatric mental health nurse practitioner licensed in Washington. His background includes inpatient and outpatient mental health care.\n\nHe founded University Place TMS Clinic to bring together Deep TMS and psychiatric care in a setting centered on each person’s needs.",
            'highlights' => ['Psychiatric mental health', 'Washington licensed', 'Inpatient & outpatient experience'],
            'photo_id' => SiteMediaSeeder::id('ayub-mwaura.jpg'),
            'show_on' => ['home', 'tms', 'contact'],
            'is_founder' => true,
            'schema_type' => 'Physician',
        ]);
        $this->add('team', TeamMember::class, [
            'name' => 'Cathy Mwangi',
            'credentials' => 'BSN, RN',
            'role_title' => 'Clinical Manager',
            'bio' => 'Part of the team supporting your care at University Place.',
            'photo_id' => SiteMediaSeeder::id('cathy-mwangi.jpg'),
            'show_on' => ['home'],
            'schema_type' => 'Person',
        ]);
    }

    private function faqs(): void
    {
        $home = [
            ['What is TMS therapy?', 'TMS uses magnetic pulses to stimulate targeted brain areas. It is an outpatient treatment that may be considered for depression when other treatments have not helped enough. [Read our TMS treatment guide.](/tms)'],
            ['How do I know if TMS is right for me?', 'A provider reviews your symptoms, past treatment, medical history, and safety considerations. Our free online self-assessment helps you prepare questions; it cannot diagnose a condition or determine eligibility.'],
            ['Is the phone consultation free?', 'Yes. The clinic offers a free initial phone consultation. Ask the team about charges for clinical evaluations and treatment, which are separate from that introductory call.'],
            ['Will my insurance cover TMS?', 'Coverage depends on your exact plan, diagnosis, treatment history, network status, and authorization requirements. Approval for depression does not automatically mean approval for OCD. Contact the clinic with the name of your plan to discuss what needs to be verified.'],
            ['How many appointments will I need?', 'TMS usually involves a series of visits over several weeks. Session length and frequency depend on your prescribed protocol. Your team will discuss the schedule before you decide to begin.'],
            ['Are there side effects or reasons I cannot have TMS?', 'Headache and scalp discomfort can occur. Seizures are a rare serious risk. Tell your provider about implants or metal near your head, seizure history, and medications so they can assess safety.'],
            ['Should I stop my medication?', 'Do not stop or change prescribed medication on your own. Discuss your current medications with your treating clinician and the TMS provider.'],
            ['Can I contact you for a family member?', 'Yes. You can call to ask general questions about care for a loved one. The clinic will explain patient consent and the evaluation steps needed to discuss individual care.'],
            ['Are TMS and Deep TMS the same thing?', 'TMS is the broader treatment category. Deep TMS is a type of TMS that uses a specialized coil design; UPTMS describes using BrainsWay technology. The prescribed device and protocol depend on the condition and clinical evaluation.'],
            ['Does the same TMS protocol treat depression and OCD?', 'Not necessarily. Depression and OCD treatment can involve different coils, stimulation targets, and session procedures. Tell the provider if you experience both so they can discuss an appropriate plan. [See the depression and OCD comparison.](/tms#treatment-differences)'],
            ['Is Deep TMS for OCD FDA-cleared?', 'The FDA authorized the BrainsWay Deep TMS system for OCD treatment in 2018. Its OCD indication is adjunctive treatment for adults. Device authorization does not determine whether a particular person is suitable or whether their insurance will cover treatment.'],
            ['What is ERP, and is it relevant to OCD?', 'Exposure and response prevention is a form of cognitive behavioral therapy used for OCD. It involves approaching triggers while learning to resist compulsions with professional guidance. Discuss ERP and medication history during your evaluation; listing this option does not mean UPTMS provides ERP therapy.'],
            ['What does a TMS session feel like?', 'You may hear clicking and feel tapping at the stimulation site. Scalp discomfort can occur. Ask about hearing protection, positioning, and how the team responds if you are uncomfortable.'],
            ['How soon will I know whether treatment is helping?', 'Improvement varies from person to person. Ask how symptoms will be assessed before, during, and after the course, and what the plan would be if progress is limited. No specific result or timeline can be promised.'],
            ['How much does TMS treatment cost?', 'Your cost depends on the prescribed course and your plan’s coverage, deductible, copay, or coinsurance. Ask for an estimate before treatment. The introductory phone consultation is free; clinical evaluations and treatment are separate.'],
            ['Do I have to live in Tacoma to contact the clinic?', 'No. The clinic is located in University Place and serves the Greater Tacoma area. Because treatment may require frequent visits, ask about appointment timing and travel before committing to a course.'],
        ];

        $tms = [
            ['What does “treatment-resistant depression” mean?', 'It describes depression that has not improved enough after appropriate treatment attempts. A clinician reviews which treatments were tried, at what dose, and for how long. An insurer may apply its own documentation criteria; a quiz cannot establish the diagnosis or authorize treatment.'],
            ['Does having intrusive thoughts mean I have OCD?', 'No single symptom establishes a diagnosis. A qualified mental health professional looks at the pattern, distress, compulsions, and impact on life, and considers other possible explanations. Seek an assessment rather than using this page to diagnose yourself.'],
            ['What should I tell the provider about past treatment?', 'Bring medication names, approximate dates, effects and side effects, therapy history, and any previous brain stimulation. For OCD, include any ERP experience. The clinic can explain how to share records through an appropriate channel.'],
            ['What is the H7 coil mentioned on the OCD page?', 'UPTMS identifies the H7 coil on its OCD service page. It is associated with the BrainsWay OCD treatment protocol. Ask the provider to explain the device and protocol planned for you, including how they differ from depression treatment.'],
            ['What does “adjunctive OCD treatment” mean?', 'Adjunctive means used as an addition to other care. BrainsWay’s OCD indication is adjunctive treatment for adults. Your treating team should explain how Deep TMS relates to your medication or psychotherapy plan.'],
            ['Will an OCD session involve discussing symptom triggers?', 'OCD protocols may include clinician-guided symptom provocation. Ask the provider whether that is part of the proposed protocol, what it involves, and how it will be supported. Do not attempt to create a treatment protocol from online instructions.'],
            ['Can I compare results from different TMS studies?', 'Only with context. Studies may involve different diagnoses, devices, protocols, and definitions of response or remission. A published percentage is not a prediction of your outcome or a verified success rate for this clinic.'],
            ['What happens if treatment does not help enough?', 'Ask in advance how response will be measured and when the provider will review the plan. Decisions about continuing, changing, or trying another treatment should be made with the treating clinician.'],
            ['Can depression coverage be used to assume OCD coverage?', 'No. Ask your insurer about coverage for the actual diagnosis and protocol being considered. Network status, prior authorization, and patient costs can differ, even under the same policy.'],
            ['Is the self-check a clinical screening instrument?', 'No. It is a short educational navigation tool created for this website. It does not calculate a validated symptom score, diagnose depression or OCD, or determine clinical or insurance eligibility. The clinic’s initial phone consultation is a separate next step.'],
        ];

        foreach (['home' => $home, 'tms' => $tms] as $group => $items) {
            foreach ($items as [$question, $answer]) {
                $this->add('faqs', Faq::class, ['group' => $group, 'question' => $question, 'answer' => $answer, 'in_schema' => true]);
            }
        }
    }

    private function testimonials(): void
    {
        foreach ([
            ['I finally feel like myself again.', 'Sarah M.'],
            ['Everyone at the clinic was so kind and knowledgeable.', 'Kevin T.'],
            ['TMS helped me get it back.', 'Linda R.'],
            ['Professional, caring', 'Keith S.'],
        ] as [$quote, $author]) {
            $this->add('testimonials', Testimonial::class, ['quote' => $quote, 'author_name' => $author, 'source_label' => 'Clinic website testimonial']);
        }
    }

    private function insurance(): void
    {
        foreach ([
            'Cigna' => 'insurance-cigna.jpg',
            'Coordinated Care' => 'insurance-coordinated-care.jpg',
            'Community Health Plan of Washington' => 'insurance-chpw.jpg',
            'Molina Healthcare' => 'insurance-molina.jpg',
            'Premera Blue Cross' => 'insurance-premera.jpg',
            'TRICARE' => 'insurance-tricare.jpg',
            'Regence BlueShield' => 'insurance-regence.jpg',
            'ProviderOne (Washington HCA)' => 'insurance-providerone.jpg',
            'Washington State Department of Labor & Industries' => 'insurance-lni.jpg',
            'Medicaid' => 'insurance-medicaid.jpg',
        ] as $name => $logo) {
            $this->add('insurance', InsurancePartner::class, ['name' => $name, 'logo_id' => SiteMediaSeeder::id($logo)]);
        }
    }

    private function contentItems(): void
    {
        foreach (['Psychiatric provider-led care', 'BrainsWay Deep TMS', 'Local University Place clinic', 'Free phone consultation'] as $title) {
            $this->add('trust-points', ContentItem::class, ['title' => $title]);
        }

        foreach ([
            ['Start a conversation', 'Take the free self-assessment or call to share what you would like to understand.'],
            ['Meet with a provider', 'Review your symptoms, treatment history, health conditions, and questions about TMS.'],
            ['Review your plan', 'Discuss clinical suitability, insurance requirements, costs, and a practical schedule.'],
            ['Begin appropriate care', 'If TMS is recommended, your team explains treatment and how your progress will be followed.'],
        ] as [$title, $body]) {
            $this->add('journey-steps', ContentItem::class, ['title' => $title, 'body' => $body]);
        }
    }

    /**
     * @param  class-string<CollectionItem>  $model
     * @param  array<string, mixed>  $fields
     */
    private function add(string $type, string $model, array $fields): void
    {
        $collection = $this->types->get($type);
        $item = new $model;

        if ($collection->fixedGroup() !== null) {
            $fields['group'] = $collection->fixedGroup();
        }

        $item->fill($fields);
        $item->is_published = true;
        $item->sort_order = (int) $collection->query()
            ->when($collection->groupColumn() !== null, fn ($q) => $q->where('group', $fields['group'] ?? null))
            ->max('sort_order') + 1;
        $item->save();

        app(SaveCollectionItem::class)->syncMedia($collection, $item);
    }
}
