<script setup lang="ts">
import LegalDocument from '@/components/legal/LegalDocument.vue';
import { LEGAL_CONTACT_EMAIL, type LegalSection } from '@/lib/legal';
import { terms } from '@/routes';
import { Link } from '@inertiajs/vue3';

const mailto = `mailto:${LEGAL_CONTACT_EMAIL}`;

const sections: LegalSection[] = [
    { id: 'who-we-are', title: 'Who we are' },
    { id: 'what-we-collect', title: 'What we collect' },
    { id: 'how-we-use-it', title: 'How we use it' },
    { id: 'what-others-can-see', title: 'What other people can see' },
    { id: 'google-drive', title: 'Google Drive and your photos' },
    { id: 'who-we-share-with', title: 'Who we share it with' },
    { id: 'cookies', title: 'Cookies and storage on your device' },
    { id: 'retention', title: 'How long we keep it' },
    { id: 'security', title: 'How we protect it' },
    { id: 'your-rights', title: 'Your rights and choices' },
    { id: 'children', title: 'Children' },
    { id: 'changes', title: 'Changes to this policy' },
    { id: 'contact', title: 'Contact us' },
];
</script>

<template>
    <LegalDocument
        title="Privacy Policy"
        description="What BloodlineHub collects, why, who can see it, and how to access or delete your information."
        updated="November 10, 2026"
        :sections="sections"
    >
        <template #intro>
            <p>
                BloodlineHub keeps records for pigeon fanciers: birds, ring
                numbers, pedigrees, pairings and race results. This policy
                explains what information we collect, why we collect it, who
                can see it, and the choices you have. It applies to the
                BloodlineHub website and the installable app.
            </p>
        </template>

        <section>
            <h2 id="who-we-are">Who we are</h2>
            <p>
                BloodlineHub ("we", "us") decides how the personal information
                described here is used, and is responsible for it under the
                Data Privacy Act of 2012 (Republic Act No. 10173) of the
                Philippines. For any privacy question or request, email
                <a :href="mailto">{{ LEGAL_CONTACT_EMAIL }}</a
                >.
            </p>
        </section>

        <section>
            <h2 id="what-we-collect">What we collect</h2>

            <h3>Information you give us</h3>
            <ul>
                <li>
                    <strong>Account details:</strong> your name, email address
                    and password. We store the password only in a scrambled
                    (hashed) form, so we never know what it is. If you turn on
                    two-factor authentication, we store its secret key and
                    recovery codes in encrypted form.
                </li>
                <li>
                    <strong>Invite requests:</strong> the email address you
                    leave on the "Request an invite" page.
                </li>
                <li>
                    <strong>Your loft records:</strong> everything you enter
                    about your birds and your loft, such as ring numbers, names,
                    colors, bloodlines, hatch dates, parents and ancestors,
                    merits, pairings, clutches, locations, loft checks, clubs,
                    one-loft races, seasons, race results, breeding plans, sales
                    listings, remarks and private notes.
                </li>
                <li>
                    <strong>Breeder details:</strong> the display name, phone
                    number, email address and postal address you add in
                    Settings → Pedigree. Each contact detail is printed on your
                    pedigrees only if you switch it on.
                </li>
                <li>
                    <strong>Photos:</strong> pictures of your birds, their
                    relatives, pedigrees and certificates. These are saved in
                    your own Google Drive, not on our servers. See
                    <a href="#google-drive">Google Drive and your photos</a>.
                </li>
                <li>
                    <strong>Messages:</strong> anything you send us when you
                    contact us.
                </li>
            </ul>

            <h3>Information collected automatically</h3>
            <ul>
                <li>
                    <strong>Sign-in sessions:</strong> when you are signed in,
                    we record your IP address, your browser and device type (the
                    "user agent") and when you were last active, so we can keep
                    you signed in and protect your account.
                </li>
                <li>
                    <strong>Server logs:</strong> our servers log requests and
                    errors, which include IP addresses, to keep BloodlineHub
                    running, fix problems and stop abuse such as repeated
                    password guessing.
                </li>
                <li>
                    <strong>Cookies and device storage:</strong> a small number
                    of cookies needed to run the site. See
                    <a href="#cookies">Cookies and storage on your device</a>.
                </li>
            </ul>

            <p>
                We do not use analytics or advertising trackers, and we do not
                collect payment details: BloodlineHub does not take payments.
                We do not ask for sensitive personal information such as
                government ID numbers, health information or religious beliefs,
                and you should not enter it in your records.
            </p>
        </section>

        <section>
            <h2 id="how-we-use-it">How we use it</h2>
            <p>We use your information to:</p>
            <ul>
                <li>
                    run BloodlineHub for you: keep your records, build
                    pedigrees, trace lineage, calculate relationships and
                    inbreeding, suggest merits from your race results, and show
                    your photos;
                </li>
                <li>
                    sign you in and keep your account secure, including
                    two-factor authentication, password resets and limits on
                    repeated attempts;
                </li>
                <li>
                    send emails about your account, such as invitations, email
                    address verification and password reset links;
                </li>
                <li>publish the pedigrees and listings you choose to share;</li>
                <li>answer your questions and requests;</li>
                <li>find and fix problems, and prevent fraud and abuse;</li>
                <li>meet our legal obligations.</li>
            </ul>
            <p>
                We process your information because it is needed to provide the
                service you signed up for, because we have a legitimate interest
                in keeping BloodlineHub secure and working, because you gave
                your consent (for example, when you connect Google Drive or show
                your contact details on pedigrees), or because the law requires
                it.
            </p>
            <p>
                We do not sell your personal information, we do not use it for
                advertising, and we do not send you marketing emails.
            </p>
        </section>

        <section>
            <h2 id="what-others-can-see">What other people can see</h2>
            <p>
                Your records are private to your account. Other users cannot
                browse your loft. Information becomes visible to others only
                when you choose to share it:
            </p>
            <ul>
                <li>
                    <strong>Shared pedigrees.</strong> Each pedigree has its own
                    link and QR code. A pedigree can be private (only you can
                    open it), protected by a password for a period you choose
                    (anyone with the link and the password can open it), or
                    public (anyone with the link can open it). An open pedigree
                    shows the bird, its ancestors and their remarks, merits and
                    photos, your breeder name, and any contact details you have
                    switched on. Private notes are never shown.
                </li>
                <li>
                    <strong>Marketplace listings.</strong> When the marketplace
                    is open, an active sales listing is public. It shows the
                    bird's name, ring number, sex, color, hatch date, photos,
                    sire and dam, the price (unless you hide it), your
                    description, the contact details you enter for the listing,
                    and your account name as the seller.
                </li>
                <li>
                    <strong>Photo links.</strong> Each photo is shown through a
                    link containing a long random code. Anyone who has a
                    photo's link can view it. People who see a shared pedigree
                    or listing can also save, print or screenshot it, and we
                    cannot take back copies made that way.
                </li>
            </ul>
            <p>
                You can make a pedigree private again or take a listing down at
                any time.
            </p>
        </section>

        <section>
            <h2 id="google-drive">Google Drive and your photos</h2>
            <p>
                Photos are stored in your own Google Drive, so they stay yours
                and count toward your Google storage. Connecting Google Drive is
                optional, but you need it to add photos.
            </p>

            <h3>What we access</h3>
            <p>
                When you connect, we ask Google only for the
                <strong>drive.file</strong> permission. It lets BloodlineHub
                create files in your Drive and use the files it created. We
                cannot see, open or change anything else in your Drive. With
                this permission we access:
            </p>
            <ul>
                <li>
                    the photos BloodlineHub saves in a "BloodlineHub" folder in
                    your Drive;
                </li>
                <li>
                    the email address of the connected Google account, and your
                    Drive storage used and available, which we show in Settings
                    → Storage so you can see which account is connected and how
                    much space is left.
                </li>
            </ul>

            <h3>What we store and why</h3>
            <ul>
                <li>
                    the access keys (tokens) Google gives us, so we can save and
                    show your photos without asking you to sign in to Google
                    each time;
                </li>
                <li>
                    the connected Google account's email address and the ID of
                    the BloodlineHub folder;
                </li>
                <li>the Drive ID of each photo, linked to your records.</li>
            </ul>
            <p>
                We use this information only to save, show, replace and delete
                your photos, and to show your connection in Settings. When you
                or someone you shared a pedigree or listing with views a photo,
                our server fetches it from your Drive. Browsers and our content
                delivery network may keep a cached copy so it loads faster.
            </p>

            <h3>What we don't do</h3>
            <ul>
                <li>We do not sell information we receive from Google.</li>
                <li>We do not use it for advertising.</li>
                <li>
                    We do not share it with anyone else, except as needed to run
                    the photo feature, to comply with the law, or with your
                    permission.
                </li>
                <li>
                    We do not let people read it, except with your permission,
                    to investigate security problems or abuse, or when the law
                    requires it.
                </li>
                <li>
                    We do not use it to develop, improve or train artificial
                    intelligence or machine learning models.
                </li>
            </ul>
            <p>
                BloodlineHub's use and transfer of information received from
                Google APIs to any other app will adhere to the
                <a
                    href="https://developers.google.com/terms/api-services-user-data-policy"
                    target="_blank"
                    rel="noopener noreferrer"
                    >Google API Services User Data Policy</a
                >, including the Limited Use requirements.
            </p>

            <h3>Disconnecting</h3>
            <p>
                In Settings → Storage you can disconnect Google Drive at any
                time. We then delete our access keys. Your photos stay in your
                Drive but no longer show in BloodlineHub until you reconnect the
                same Google account. You can also remove BloodlineHub in
                <a
                    href="https://myaccount.google.com/connections"
                    target="_blank"
                    rel="noopener noreferrer"
                    >your Google account's third-party connections</a
                >. Doing that may stop BloodlineHub from showing photos it saved
                earlier, even if you connect again later. Deleting your
                BloodlineHub account does not delete the photos in your Drive:
                you can delete the BloodlineHub folder yourself.
            </p>
        </section>

        <section>
            <h2 id="who-we-share-with">Who we share it with</h2>
            <p>
                We share personal information only with service providers that
                help us run BloodlineHub, and only what they need to do that
                work:
            </p>
            <ul>
                <li>
                    <strong>Hosting providers</strong> that run our servers,
                    database and content delivery network;
                </li>
                <li>
                    <strong>an email delivery provider</strong> that sends
                    account emails;
                </li>
                <li>
                    <strong>Google</strong>, when you connect Google Drive, to
                    store your photos;
                </li>
                <li>
                    <strong>Bunny Fonts</strong> (fonts.bunny.net), which
                    supplies the site's typefaces. Your browser sends your IP
                    address to it when it loads the fonts.
                </li>
            </ul>
            <p>
                We may also disclose information when the law requires it, to
                protect the rights, property or safety of our users, the public
                or BloodlineHub, or as part of a merger, sale or transfer of
                BloodlineHub. In the last case we will tell you before your
                information becomes subject to a different privacy policy.
            </p>
            <p>
                Some of these providers store or process information outside
                the Philippines. When that happens we take reasonable steps so
                that your information is protected to the standard this policy
                and Philippine law require.
            </p>
        </section>

        <section>
            <h2 id="cookies">Cookies and storage on your device</h2>
            <p>We use only cookies that BloodlineHub needs to work:</p>
            <ul>
                <li>a session cookie that keeps you signed in;</li>
                <li>
                    a security cookie (XSRF-TOKEN) that protects your forms
                    from being submitted by other websites;
                </li>
                <li>
                    a "remember me" cookie, if you tick that box when you log
                    in;
                </li>
                <li>
                    preference cookies that remember your appearance setting and
                    whether the sidebar is open.
                </li>
            </ul>
            <p>
                Your browser also keeps some things on your device: unsaved loft
                check counts (so a count survives a lost connection), your
                display preferences, whether you have closed the cookie notice,
                and, if you install the app, the app's
                files so it opens quickly. You can clear these in your browser
                settings at any time. We do not use advertising or tracking
                cookies.
            </p>
        </section>

        <section>
            <h2 id="retention">How long we keep it</h2>
            <ul>
                <li>
                    <strong>Your account and records</strong> are kept while
                    your account is open. When you delete your account in
                    Settings → Profile, we delete your account and your loft
                    records. Copies in our backups are removed as the backups
                    are replaced.
                </li>
                <li>
                    <strong>Sign-in sessions</strong> expire after 30 days
                    without activity.
                </li>
                <li>
                    <strong>Invite requests</strong> are kept until we send your
                    invitation or close the waiting list, or sooner if you ask
                    us to remove yours.
                </li>
                <li>
                    <strong>Server logs</strong> are kept for a limited time for
                    security and troubleshooting, then deleted.
                </li>
                <li>
                    <strong>Photos</strong> stay in your Google Drive until you
                    delete them there.
                </li>
            </ul>
            <p>
                We may keep some information longer if the law requires it or
                to resolve a dispute.
            </p>
        </section>

        <section>
            <h2 id="security">How we protect it</h2>
            <p>
                We use encrypted connections (HTTPS), store passwords only in
                hashed form, encrypt two-factor secrets, offer two-factor
                authentication, limit repeated sign-in and password attempts,
                and restrict who can access our systems. No service can be
                perfectly secure. If a breach affects your personal information,
                we will notify you and the National Privacy Commission as the
                law requires.
            </p>
        </section>

        <section>
            <h2 id="your-rights">Your rights and choices</h2>
            <p>
                Under the Data Privacy Act you have the right to be informed
                about how your information is used, to access it, to have it
                corrected, to object to its processing, to have it erased or
                blocked, to receive a copy in a portable format, and to be
                compensated for damages caused by unlawful processing.
            </p>
            <p>You can do much of this yourself in BloodlineHub:</p>
            <ul>
                <li>change your name and email in Settings → Profile;</li>
                <li>edit or delete any of your records;</li>
                <li>
                    hide contact details from your pedigrees in Settings →
                    Pedigree;
                </li>
                <li>disconnect Google Drive in Settings → Storage;</li>
                <li>delete your account in Settings → Profile.</li>
            </ul>
            <p>
                For anything else, such as a copy of your data, email
                <a :href="mailto">{{ LEGAL_CONTACT_EMAIL }}</a
                >. We may need to confirm your identity before acting on a
                request, and we will reply within the time the law requires.
                If you are not satisfied with our response, you can file a
                complaint with the
                <a
                    href="https://privacy.gov.ph"
                    target="_blank"
                    rel="noopener noreferrer"
                    >National Privacy Commission</a
                >.
            </p>
        </section>

        <section>
            <h2 id="children">Children</h2>
            <p>
                BloodlineHub is not meant for children. Anyone under 18 should
                use it only with a parent or guardian's permission. If you
                believe a child has given us personal information without that
                permission, contact us and we will delete it.
            </p>
        </section>

        <section>
            <h2 id="changes">Changes to this policy</h2>
            <p>
                We may update this policy as BloodlineHub changes. We will post
                the new version on this page and update the date at the top. If
                a change significantly affects how we use your information, we
                will tell you by email or in the app before it takes effect.
            </p>
            <p>
                How you may use BloodlineHub is covered by our
                <Link :href="terms()">Terms and Conditions</Link>.
            </p>
        </section>

        <section>
            <h2 id="contact">Contact us</h2>
            <p>
                Questions, requests or complaints about your privacy:
                <a :href="mailto">{{ LEGAL_CONTACT_EMAIL }}</a
                >.
            </p>
        </section>
    </LegalDocument>
</template>
