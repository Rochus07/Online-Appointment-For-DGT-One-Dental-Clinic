<?php
/**
 * Terms & Conditions and Privacy Policy (migration 065).
 *
 * The admin edits both texts on Settings > Legal & Policy Settings; they are
 * stored in `legal_documents`. Until a document is saved, the built-in
 * default below is used. terms.php / privacy.php render them, and the
 * registration form + landing-page footer link to those two pages.
 *
 * Text format (kept simple so a non-technical admin can edit it):
 *   ## Heading          a section heading
 *   - item              a bullet (consecutive lines form one list)
 *   > text              a highlighted notice box
 *   **bold**  *italic*  inline emphasis
 *   a blank line        starts a new paragraph; a single line break inside a
 *                       paragraph is kept as a line break
 */
declare(strict_types=1);

const LEGAL_DOCS = ['terms' => 'Terms and Conditions', 'privacy' => 'Privacy Policy'];

// "Last updated" date shown for the built-in default texts (bump it when legalDefault() changes).
const LEGAL_DEFAULT_UPDATED = '2026-10-18';

// Shared wrapper classes for the rendered HTML (bare h3/p/ul/li).
if (!defined('TC_BODY_CLASSES')) {
    define('TC_BODY_CLASSES', 'text-[13px] leading-relaxed text-navy-800 dark:text-steel-100 '
        . '[&_h3]:mb-1.5 [&_h3]:mt-[18px] [&_h3]:text-sm [&_h3]:font-bold [&_h3]:text-navy-900 [&_h3]:dark:text-steel-50 '
        . '[&_p]:mb-2.5 [&_ul]:mb-2.5 [&_ul]:ml-5 [&_ul]:list-disc [&_li]:mb-1.5 [&_strong]:font-bold');
}

function legalDefault(string $doc): string
{
    if ($doc === 'privacy') {
        return <<<'TXT'
DGT One Dental Center - Bulacan ("the Clinic", "we", "us") respects your privacy. This Privacy Policy explains how we collect, use, and protect your personal information when you use our online patient portal.

## 1. Data Privacy and Consent (Republic Act No. 10173)
In compliance with the **Data Privacy Act of 2012 (RA 10173)**, its Implementing Rules and Regulations, and the issuances of the National Privacy Commission, you expressly and voluntarily consent to the collection, recording, use, storage, and processing by the Clinic of your personal and sensitive personal information, including but not limited to:

- your name, date of birth, contact details, and address;
- your dental and medical history, clinical findings, tooth charting, treatment notes, prescriptions, and diagnostic records;
- appointment history, and billing, payment, and insurance information.

## 2. How We Use Your Information
This information is processed solely for legitimate purposes connected with your dental care: scheduling and managing appointments, providing and documenting treatment, issuing invoices and official receipts, processing payments, complying with legal and regulatory obligations, and contacting you about your care.

## 3. Who Can Access It
Access is limited to the Clinic's authorised dentists, front-desk, and cashier personnel on a need-to-know basis.

## 4. Retention
We retain your records for the period required by Philippine law and professional practice standards, after which they are securely disposed of.

## 5. Your Rights
Under RA 10173 you have the right to be informed, to object, to access and to rectify your data, to erasure or blocking, to data portability, to file a complaint with the National Privacy Commission, and to damages. Withdrawing your consent may prevent the Clinic from continuing to provide portal services or dental care that depend on this information.

## 6. Contact
To exercise any of these rights, or for any privacy concern, contact:

**DGT One Dental Center - Bulacan**
B-6L-47 Delrey Bldg., Bgy. Tungkong Mangga, San Jose Del Monte, Bulacan
Phone: 09764484252
Email: customerservice@dgtonedentalph.com
TXT;
    }
    return <<<'TXT'
These Terms and Conditions ("Terms") govern your use of the online patient portal of **DGT One Dental Center - Bulacan** ("the Clinic", "we", "us"), located at B-6L-47 Delrey Bldg., Bgy. Tungkong Mangga, San Jose Del Monte, Bulacan. By creating an account, booking an appointment, or otherwise using the portal, you ("the patient", "you") confirm that you have read, understood, and agreed to be bound by these Terms.

## 1. Acceptance of Terms
You must be at least 18 years old, or the parent or legal guardian of the patient being registered, to create an account. If you are booking on behalf of a family member or dependent, you represent that you are authorised to share their information and to consent to their treatment.

## 2. Data Privacy
How the Clinic collects, uses, and protects your personal and sensitive personal information under the **Data Privacy Act of 2012 (RA 10173)** is described in our Privacy Policy, which forms part of these Terms.

## 3. Appointment Booking, Rescheduling, and Cancellation
- Every online booking is a **request** and is not confirmed until the Clinic's front desk reviews and approves it and assigns a dentist. The Clinic may decline a request and will provide a reason.
- The Clinic is open **Monday to Saturday by appointment and is closed on Sundays** and on posted clinic holidays. Requested dates and times are subject to availability and to slot capacity.
- You may **cancel or reschedule** an appointment through the portal, and a reason is required for either.

**Cancellation Policy**

- **Non-Refundable Deposits:** All appointment and booking reservation fees paid during online booking are strictly non-refundable upon cancellation.
- **Notice Period:** Patients who wish to cancel or modify their scheduled visit must notify the Clinic at least **24 hours** prior to the appointment date.
- **Rescheduling Option:** In lieu of cancellation, and provided you reschedule at least 24 hours before the scheduled start time, you may move your appointment to an open time slot at no extra charge, subject to doctor availability; your reservation fee carries over automatically to the new date (see Section 4).
- **No-Show Policy:** Failure to arrive for a confirmed appointment without prior notification may result in cancellation of the slot and forfeiture of all fees paid, including the **{{reservation_fee}}** reservation fee.
- If you arrive more than 15 minutes late, or do not appear for a confirmed appointment, the Clinic may treat the slot as missed. **The Clinic reserves the right to re-assign missed or unconfirmed slots to other patients** without notice.
- Repeated missed or short-notice appointments may result in a requirement to pay the reservation fee in advance for future bookings, or in suspension of online booking privileges.

## 4. Reservation Fees
> **Appointment fee: {{reservation_fee}} per booking.** It is paid upfront when you book online, it is **non-refundable**, and it is **forfeited if you cancel or do not show up**. It is credited toward your bill when you attend.

- A **reservation fee of {{reservation_fee}}** is required to hold an appointment slot. The same amount is shown to you again during booking before you submit. This fee is **non-refundable** and is **not returned in cash under any circumstances**.
- When your visit takes place, the reservation fee is **credited toward the total cost** of the services actually rendered.
- The reservation fee is **transferable to a rescheduled appointment** at no extra charge, **provided you reschedule at least 24 hours before** the scheduled start time. It carries over automatically to the new date.
- The reservation fee is **forfeited** if you cancel the appointment, do not show up, arrive too late to be treated, or fail to reschedule before the 24-hour cut-off.
- If the *Clinic* cancels or is unable to accommodate a confirmed appointment, the reservation fee is **credited in full to your next booking**.

## 5. Payment Terms, Reference Numbers, and Proof of Payment
- Online reservation and settlement payments are accepted by **GCash** and **bank transfer** only. Balances may also be settled in cash at the Clinic.
- You are responsible for entering an **accurate and complete reference number** for every electronic payment (for GCash, exactly 13 digits; for bank transfers, the bank reference number as issued by your bank). A reference number that has already been used for another transaction will be rejected.
- When asked, you must **upload a clear, unedited image of the official payment receipt** or confirmation screen. Submitting an incorrect, altered, illegible, reused, or fraudulent reference number or receipt is a material breach of these Terms and may result in the cancellation of your appointment and closure of your account, without prejudice to any legal action available to the Clinic.
- Payments are verified by the Clinic's cashier. An appointment funded only by a reservation credit remains *unpaid* until the balance is settled. Official Receipts are issued in accordance with Bureau of Internal Revenue regulations.

## 6. Not for Medical Emergencies
> The patient portal is intended only for the **elective scheduling** of routine and non-urgent dental care. It is **not monitored in real time** and must **never** be used to report a medical or dental emergency.

If you are experiencing severe pain, uncontrolled bleeding, facial swelling that affects breathing or swallowing, trauma to the jaw or teeth, a reaction to medication, or any life-threatening condition, **call the Clinic directly at 09764484252** during clinic hours, proceed to the nearest hospital emergency department, or call your local emergency services immediately. Booking a portal appointment does not establish urgent or on-call care.

## 7. Patient Responsibilities
- Provide accurate, current, and complete personal, contact, and medical information, and keep it updated.
- Disclose all relevant medical conditions, allergies, medications, and pregnancy status before treatment.
- Use the portal only for your own bookings or those of dependents you are authorised to represent.
- Follow pre- and post-treatment instructions given by the dentist.

## 8. Account Security
You are responsible for maintaining the confidentiality of your login credentials and for all activity under your account. Notify the Clinic immediately of any unauthorised use. The Clinic may suspend or archive an account that is inactive, appears compromised, or is used in violation of these Terms; archiving is a reversible deactivation and does not delete your clinical records.

## 9. Limitation of Liability
The portal is provided on an "as available" basis. The Clinic is not liable for scheduling disruptions caused by internet outages, power interruptions, third-party payment providers, or events beyond its reasonable control. Nothing in these Terms limits the Clinic's obligations under the Data Privacy Act or applicable consumer-protection and health regulations.

## 10. Changes to These Terms and Contact
The Clinic may update these Terms from time to time. Material changes will be posted on this page with a new "Last updated" date, and continued use of the portal after that date constitutes acceptance. For questions, requests, or privacy concerns, contact:

**DGT One Dental Center - Bulacan**
B-6L-47 Delrey Bldg., Bgy. Tungkong Mangga, San Jose Del Monte, Bulacan
Phone: 09764484252
Email: customerservice@dgtonedentalph.com

*By ticking "I agree to the Terms of Service and Privacy Policy" on the sign-up page, you acknowledge that you have read and understood this agreement and the Privacy Policy, and you give your informed consent to the processing of your personal and sensitive personal information as described there.*
TXT;
}

/**
 * The current text of a document: ['body' => string, 'updated_at' => ?string,
 * 'custom' => bool]. Falls back to the default if nothing is saved (or the
 * table is missing on an install that hasn't run migration 065).
 */
function legalDoc(string $doc): array
{
    if (!isset(LEGAL_DOCS[$doc])) $doc = 'terms';
    try {
        $st = getDB()->prepare('SELECT body, updated_at FROM legal_documents WHERE doc_key = ?');
        $st->execute([$doc]);
        $row = $st->fetch();
        if ($row && trim((string) $row['body']) !== '') {
            return ['body' => (string) $row['body'], 'updated_at' => (string) $row['updated_at'], 'custom' => true];
        }
    } catch (Throwable $e) {
        // fall through to the default
    }
    return ['body' => legalDefault($doc), 'updated_at' => LEGAL_DEFAULT_UPDATED, 'custom' => false];
}

/** Inline markup: escape, then **bold** and *italic*. */
function legalInline(string $s): string
{
    $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $s);
    return $s;
}

/** Turns the admin's plain-text format (see the header) into safe HTML. */
function renderLegal(string $text): string
{
    // {{reservation_fee}} shows the current fee from Settings (2026-10-18).
    if (str_contains($text, '{{reservation_fee}}')) {
        $fee = 500.0;
        try { $fee = (float) setting('reservation_fee', '500'); } catch (Throwable $e) {}
        $text = str_replace('{{reservation_fee}}', '₱' . number_format($fee, 2), $text);
    }
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $html  = '';
    $para  = [];
    $list  = [];
    $flushPara = function () use (&$para, &$html) {
        if ($para) {
            $html .= '<p>' . implode('<br>', array_map('legalInline', $para)) . "</p>\n";
            $para = [];
        }
    };
    $flushList = function () use (&$list, &$html) {
        if ($list) {
            $html .= "<ul>\n" . implode('', array_map(fn ($li) => '<li>' . legalInline($li) . "</li>\n", $list)) . "</ul>\n";
            $list = [];
        }
    };
    foreach ($lines as $raw) {
        $line = rtrim($raw);
        $t = ltrim($line);
        if ($t === '') { $flushPara(); $flushList(); continue; }
        if (str_starts_with($t, '## ')) {
            $flushPara(); $flushList();
            $html .= '<h3>' . legalInline(trim(substr($t, 3))) . "</h3>\n";
        } elseif (preg_match('/^[-*]\s+(.*)$/', $t, $m) && !str_starts_with($t, '**')) {
            $flushPara();
            $list[] = $m[1];
        } elseif (str_starts_with($t, '> ')) {
            $flushPara(); $flushList();
            $html .= '<p class="!mb-2.5 rounded-md border-l-[3px] border-[#d98a10] bg-[#fbf0dd] px-3 py-2.5 dark:bg-warn/20">'
                   . legalInline(trim(substr($t, 2))) . "</p>\n";
        } else {
            $flushList();
            $para[] = $t;
        }
    }
    $flushPara(); $flushList();
    return $html;
}
