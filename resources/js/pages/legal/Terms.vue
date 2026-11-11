<script setup lang="ts">
import LegalDocument from '@/components/legal/LegalDocument.vue';
import { LEGAL_CONTACT_EMAIL, type LegalSection } from '@/lib/legal';
import { privacy } from '@/routes';
import { Link } from '@inertiajs/vue3';

const mailto = `mailto:${LEGAL_CONTACT_EMAIL}`;

const sections: LegalSection[] = [
    { id: 'agreement', title: 'Agreeing to these terms' },
    { id: 'eligibility', title: 'Who can use BloodlineHub' },
    { id: 'account', title: 'Your account' },
    { id: 'your-content', title: 'Your content' },
    { id: 'photos', title: 'Photos and Google Drive' },
    { id: 'sharing', title: 'Shared pedigrees and listings' },
    { id: 'sales', title: 'Buying and selling birds' },
    { id: 'records', title: 'Pedigree and breeding information' },
    { id: 'acceptable-use', title: 'Acceptable use' },
    { id: 'service', title: 'The service and fees' },
    { id: 'termination', title: 'Closing or suspending accounts' },
    { id: 'ownership', title: 'Our rights' },
    { id: 'disclaimers', title: 'Disclaimers' },
    { id: 'liability', title: 'Limitation of liability' },
    { id: 'indemnity', title: 'Your responsibility for claims' },
    { id: 'law', title: 'Governing law and disputes' },
    { id: 'changes', title: 'Changes to these terms' },
    { id: 'general', title: 'General' },
    { id: 'contact', title: 'Contact us' },
];
</script>

<template>
    <LegalDocument
        title="Terms and Conditions"
        description="The rules for using BloodlineHub: your account, your records, sharing pedigrees, selling birds, and our responsibilities."
        updated="November 11, 2026"
        :sections="sections"
    >
        <template #intro>
            <p>
                These terms are an agreement between you and BloodlineHub ("we",
                "us"). They cover the BloodlineHub website, the installable app
                and everything you do with them. Please read them before you use
                BloodlineHub.
            </p>
        </template>

        <section>
            <h2 id="agreement">Agreeing to these terms</h2>
            <p>
                By creating an account or using BloodlineHub, you agree to these
                terms. If you do not agree, do not use BloodlineHub. Our
                <Link :href="privacy()">Privacy Policy</Link> explains how we
                handle your personal information and forms part of these terms.
            </p>
        </section>

        <section>
            <h2 id="eligibility">Who can use BloodlineHub</h2>
            <ul>
                <li>
                    You must be at least 18 years old, or have the permission of
                    a parent or guardian who agrees to these terms for you.
                </li>
                <li>
                    Accounts are currently by invitation. We decide who receives
                    an invitation.
                </li>
                <li>
                    An account is for one person or one loft. If you use
                    BloodlineHub for a loft or organization, you confirm you are
                    allowed to accept these terms for it.
                </li>
            </ul>
        </section>

        <section>
            <h2 id="account">Your account</h2>
            <ul>
                <li>
                    Give accurate details when you sign up and keep them
                    current.
                </li>
                <li>
                    Keep your password secret. We recommend turning on
                    two-factor authentication in Settings.
                </li>
                <li>
                    You are responsible for what happens in your account. Tell
                    us straight away at
                    <a :href="mailto">{{ LEGAL_CONTACT_EMAIL }}</a> if you think
                    someone else has used it.
                </li>
            </ul>
        </section>

        <section>
            <h2 id="your-content">Your content</h2>
            <p>
                "Your content" means everything you put into BloodlineHub: your
                birds, ring numbers, pedigrees, ancestors, merits, race results,
                notes, listings and photos.
            </p>
            <ul>
                <li>
                    <strong>You own it.</strong> We do not claim ownership of
                    your content.
                </li>
                <li>
                    <strong>You let us use it to run the service.</strong> You
                    give us a non-exclusive, worldwide, royalty-free permission
                    to store, copy, process and display your content only as
                    needed to operate BloodlineHub for you. That includes
                    showing it to other people when you share a pedigree or
                    publish a listing. This permission ends when you delete the
                    content or your account, apart from copies other people
                    already made of content you shared and copies in backups
                    until they are replaced.
                </li>
                <li>
                    <strong>You are responsible for it.</strong> Make sure your
                    content is accurate and that you have the right to use it,
                    including photos taken by other people and pedigree
                    information supplied by other breeders.
                </li>
            </ul>
        </section>

        <section>
            <h2 id="photos">Photos and Google Drive</h2>
            <p>
                Photos are saved in your own Google Drive, in a folder
                BloodlineHub creates. Your Google account is governed by
                Google's own terms, and the photos count toward your Google
                storage. If you disconnect Google Drive, remove BloodlineHub's
                access in your Google account, delete the photos from your
                Drive, or Google is unavailable, your photos may stop showing in
                BloodlineHub and on anything you have shared. We are not
                responsible for Google's service.
            </p>
        </section>

        <section>
            <h2 id="sharing">Shared pedigrees and listings</h2>
            <p>
                When you make a pedigree public or password-protected, or
                publish a sales listing, anyone with the link (and the password,
                where there is one) can see it. They may save, print, screenshot
                or pass it on, and we cannot recall those copies. Only share
                what you are comfortable making public, including the contact
                details you choose to show. You can make a pedigree private or
                take a listing down at any time.
            </p>
        </section>

        <section>
            <h2 id="sales">Buying and selling birds</h2>
            <p>
                BloodlineHub lets breeders list birds and show their pedigrees,
                but we are not a party to any sale between users. In particular:
            </p>
            <ul>
                <li>
                    we do not check birds, ring numbers, pedigrees, race
                    results, ownership, health or the people listing them;
                </li>
                <li>
                    we do not handle payment, delivery or shipping, and we do
                    not act as an agent for buyers or sellers;
                </li>
                <li>
                    buyers and sellers deal with each other directly and are
                    responsible for settling any disagreement between them.
                </li>
            </ul>
            <p>
                Sellers must describe birds honestly and have the right to sell
                them. Everyone must follow the laws that apply to selling,
                transporting and caring for animals.
            </p>
        </section>

        <section>
            <h2 id="records">Pedigree and breeding information</h2>
            <p>
                Relationship and inbreeding figures, lineage, merit suggestions,
                standings and other results are worked out from the information
                users enter. They can be wrong if that information is incomplete
                or incorrect. They are tools to help you keep and read your
                records, not a guarantee of a bird's ancestry, ability or value,
                and not veterinary or professional advice.
            </p>
            <p>
                Race results and merits are recorded by users and are not
                verified by BloodlineHub or by any club, federation or ring
                authority. BloodlineHub is not affiliated with any racing club,
                federation or ring authority.
            </p>
            <p>
                We work to keep BloodlineHub reliable, but you should keep your
                own copies of records that matter to you, for example by
                printing or saving your pedigrees as PDF.
            </p>
        </section>

        <section>
            <h2 id="acceptable-use">Acceptable use</h2>
            <p>When you use BloodlineHub, do not:</p>
            <ul>
                <li>break any law or help anyone else break one;</li>
                <li>
                    post false or misleading pedigrees, results or listings, or
                    use BloodlineHub to deceive buyers;
                </li>
                <li>
                    upload content you do not have the right to use, or content
                    that is unlawful, abusive or harassing;
                </li>
                <li>
                    try to get into other people's accounts or data, or get
                    around passwords, rate limits or other protections;
                </li>
                <li>
                    scrape or copy the service in bulk, overload it, or upload
                    malicious code;
                </li>
                <li>
                    resell or rent BloodlineHub, or use it to build a competing
                    service;
                </li>
                <li>
                    use it in connection with animal cruelty or the trade of
                    animals that may not legally be sold.
                </li>
            </ul>
            <p>
                If you find a security problem, please report it to
                <a :href="mailto">{{ LEGAL_CONTACT_EMAIL }}</a> rather than
                exploiting it.
            </p>
        </section>

        <section>
            <h2 id="service">The service and fees</h2>
            <p>
                BloodlineHub has a free plan and a paid Premium plan. What
                Premium adds, and its prices, are shown in the app under Plan
                &amp; billing. Prices may change, but a change never affects a
                period you have already paid for.
            </p>
            <p>
                <strong>When Premium ends.</strong> Your records stay and the
                free features keep working. Premium features lock again until
                you renew, and pedigrees you shared online, including through a
                printed QR code, stop opening for other people.
            </p>
            <p>
                <strong>Paying for Premium.</strong> You pay by bank transfer or
                e-wallet to one of the accounts shown on the Plan &amp; billing
                page, then upload your receipt there. Premium starts once we
                have confirmed that the money arrived, and lasts for the period
                of the plan you paid for. If you pay while Premium is still
                active, the new period starts when the current one ends. Premium
                does not renew by itself: you are never charged unless you send
                another payment.
            </p>
            <p>
                Send the amount shown for your plan. You pay any fees your bank
                or e-wallet charges. If we cannot confirm a payment, for example
                because the amount or reference does not match, we will tell you
                why, and we will return any money we received but cannot match
                to a plan.
            </p>
            <p>
                <strong>Refunds.</strong> Once Premium has started, payments are
                not refundable, except where the law requires a refund, or where
                we stop offering Premium or close your account without you
                having broken these terms. In those cases we refund the unused
                part of the period you paid for.
            </p>
            <p>
                We keep improving BloodlineHub, so features may be added,
                changed or removed, and some, like the marketplace, may be shown
                as "coming soon" before they open. We may suspend or stop part
                or all of the service. When a change takes away something
                important to you, we will give reasonable notice where we can so
                you have time to keep copies of your records.
            </p>
        </section>

        <section>
            <h2 id="termination">Closing or suspending accounts</h2>
            <p>
                You can delete your account at any time in Settings → Profile.
                Deleting it permanently removes your account and records as the
                Privacy Policy describes. Photos stay in your Google Drive.
            </p>
            <p>
                We may suspend or close your account, or remove content, if you
                break these terms, if you put other users or BloodlineHub at
                risk, or if the law requires it. Where it is reasonable and
                lawful, we will tell you why and give you a chance to respond or
                to save your records first.
            </p>
        </section>

        <section>
            <h2 id="ownership">Our rights</h2>
            <p>
                The BloodlineHub name, logo, design and software belong to us.
                We give you a personal, non-exclusive, non-transferable right to
                use BloodlineHub under these terms. You may print and share your
                own pedigrees and records. If you send us ideas or feedback, we
                may use them without owing you anything.
            </p>
        </section>

        <section>
            <h2 id="disclaimers">Disclaimers</h2>
            <p>
                To the extent the law allows, BloodlineHub is provided "as is"
                and "as available". We do not promise that it will always be
                available, error-free or secure, or that information in it,
                including information entered by other users, is accurate or
                complete.
            </p>
        </section>

        <section>
            <h2 id="liability">Limitation of liability</h2>
            <p>To the extent the law allows:</p>
            <ul>
                <li>
                    we are not liable for indirect, incidental, special or
                    consequential losses, or for lost profits, lost sales or
                    lost data;
                </li>
                <li>
                    we are not liable for dealings between users, including the
                    sale, purchase, health, ancestry or performance of any bird;
                </li>
                <li>
                    our total liability for any claim relating to BloodlineHub
                    is limited to the greater of the amount you paid us in the
                    12 months before the claim and ₱5,000.
                </li>
            </ul>
            <p>
                Nothing in these terms limits liability that cannot be limited
                by law, such as liability for fraud, bad faith or gross
                negligence.
            </p>
        </section>

        <section>
            <h2 id="indemnity">Your responsibility for claims</h2>
            <p>
                If someone makes a claim against us because of your content,
                your sales or purchases, or your breach of these terms, you
                agree to cover our reasonable costs and losses from that claim,
                to the extent the law allows.
            </p>
        </section>

        <section>
            <h2 id="law">Governing law and disputes</h2>
            <p>
                These terms are governed by the laws of the Republic of the
                Philippines. If you have a dispute with us, please contact us
                first at <a :href="mailto">{{ LEGAL_CONTACT_EMAIL }}</a> so we
                can try to resolve it informally. If we cannot resolve it within
                30 days, either of us may bring it before the proper courts of
                the Philippines. This does not take away any rights you have
                under consumer protection laws that cannot be waived.
            </p>
        </section>

        <section>
            <h2 id="changes">Changes to these terms</h2>
            <p>
                We may update these terms. We will post the new version on this
                page and update the date at the top. If a change is significant,
                we will tell you by email or in the app before it takes effect.
                If you keep using BloodlineHub after the change takes effect,
                you accept the new terms. If you do not agree, you can delete
                your account.
            </p>
        </section>

        <section>
            <h2 id="general">General</h2>
            <ul>
                <li>
                    These terms and the Privacy Policy are the whole agreement
                    between you and us about BloodlineHub.
                </li>
                <li>
                    If any part of these terms cannot be enforced, the rest
                    still applies.
                </li>
                <li>
                    If we do not enforce a term straight away, we can still
                    enforce it later.
                </li>
                <li>
                    You may not transfer your rights under these terms to anyone
                    else. We may transfer ours as part of a merger, sale or
                    reorganization of BloodlineHub.
                </li>
            </ul>
        </section>

        <section>
            <h2 id="contact">Contact us</h2>
            <p>
                Questions about these terms:
                <a :href="mailto">{{ LEGAL_CONTACT_EMAIL }}</a
                >.
            </p>
        </section>
    </LegalDocument>
</template>
