# SOP: Sending Files to Customers with the File Sharing Application

| | |
|---|---|
| **Audience** | Internal staff (sales, parts, service, admin, marketing) |
| **Purpose** | Securely send quotes, manuals, reports, photos and other documents to customers without using email attachments |
| **Application URL** | [INSERT INTERNAL URL] |
| **Owner** | [INSERT OWNER, e.g. IT] |
| **Version / date** | 1.0 / [INSERT DATE] |

> Values marked **(default)** below come from the application's configuration. IT may have changed them. Confirm with IT where it matters.

---

## 1. When to use this

Use the application when:
- Files are too large or too sensitive for regular email.
- You need an expiry date, a password, or a record of who accessed the files.

Do **not** use it for:
- Anything that must be filed as an official record. The system of record stays **IntelliDealer** (parts, service, accounting) and **ClientLink / Salesforce** (customer and sales records). Save a copy of the final document there as well.

## 2. Before you start

- [ ] You can sign in (**Sign in with Microsoft** using your work account).
- [ ] Files are final and reviewed. Quotes, prices, trade-in values, financing terms and warranty wording must already be approved by the right person (e.g. Sales Manager). This tool does not check content.
- [ ] You removed anything the customer should not see: dealer cost, margins, John Deere program details, other customers' information.
- [ ] You avoided sensitive personal data (SIN, driver's licence, date of birth, banking or credit details) unless there is an approved business need. If there is, use a password and OTP (see 4.1), and confirm with your manager first.
- [ ] You have the customer's correct email address, taken from ClientLink / Salesforce.
- [ ] File types are allowed. Blocked by default: `exe, bat, cmd, com, scr, pif, msi, dll, ps1, ps2, psc1, psc2, vbs, vbe, jse, wsf, wsh, reg, inf, cpl`. For several files, zip them or send them as separate files in one bundle.
- [ ] Size and count are within limits: **5 MB per file and 100 files per bundle (default)**. Ask IT if you need more.

## 3. Key terms

| Term | Meaning |
|---|---|
| **Bundle** | One share: a set of files plus its settings. |
| **Invitation mode** (recommended) | Each recipient gets their own emailed link. Only invited people can open it. |
| **OTP** | One-time 6-digit code emailed to the recipient to confirm identity. Expires after **15 minutes**, with a limit of **5 attempts (default)**. |
| **Static link** | Anyone with the link can open the files. Less secure. Only available if your group permits it. |
| **Approval** | Some groups need a reviewer to approve a bundle before it is sent. |

## 4. Procedure

### Step 1: Sign in and start a bundle
1. Go to [INSERT INTERNAL URL] and click **Sign in with Microsoft**.
2. On the homepage, click **Create new upload**.

### Step 2: Settings
Complete the settings page:

| Field | What to enter |
|---|---|
| **Title** | Clear and specific, e.g. `Quote [QUOTE #] – [CUSTOMER NAME]`. The customer sees this. |
| **Description** | Short, plain message, e.g. what is attached and what you need from them. No prices or commitments unless already approved. |
| **Expiry** | Shortest period that works. Typical: 1 week for quotes, 1 month for manuals. Avoid **Forever** for anything sensitive. |
| **Password** (optional) | Recommended for financial or personal documents. Also encrypts the ZIP. |
| **Share mode** | **Invitation** (default and recommended). Use static link only with manager approval. |
| **Recipients** | Customer email(s). Separate with commas or new lines. Each person gets their own link. |
| **Require email verification (OTP)** | Leave **checked**. Only uncheck if allowed by policy and the files are low risk. |

### Step 3: Add files
1. Drag and drop files or click to browse.
2. Check the file list: correct names, no duplicates, nothing missing.
3. Remove any wrong file with the delete button. You can add and remove files while the bundle is still a **draft**.

### Step 4: Complete
1. Re-check title, recipients (typos!), expiry and files. **Completed bundles are locked and cannot be edited.**
2. Click **Complete**.
3. What happens next depends on your group's policy:
   - **No approval needed:** invitations are emailed right away.
   - **Approval required:** the bundle shows **Pending approval**. A reviewer approves or denies it, and you get an email either way. See section 6.

### Step 5: Confirm and follow up
1. On the final step, confirm the recipient list shows as invited.
2. Tell the customer to watch for the email (sender is our system, not you personally). A short heads-up by phone or your normal email is recommended so it is not mistaken for spam. **Never put the password in the same email as the link.** Give it by phone or text.
3. Record in ClientLink / Salesforce that files were shared (what, when, to whom). Do not paste the links or passwords there.

## 5. Managing a bundle after sending

| Situation | Action |
|---|---|
| Customer did not get the email | Ask them to check spam/junk. Confirm the address. On the bundle page, use **Resend** for that recipient. |
| Wrong email address, or sent in error | On the bundle page, use **Revoke** for that recipient right away. Report it to your manager and IT if sensitive files were involved (possible privacy incident under PIPEDA). |
| Customer needs more time | Bundles cannot be edited once completed. Create a new bundle. |
| Customer cannot open the OTP | Codes expire. They can request a new one on the invitation page (hourly limit applies). Resend if needed. |
| Customer lost the password | Give it again by phone. Never by the same email as the link. |
| File was rejected | Blocked type or over the size limit. Convert, compress or split, or contact IT. |
| Draft you did not finish | You can reopen it from the homepage. Unfinished drafts are deleted automatically after about 72 hours (default). |

## 6. Approval workflow (if your group requires it)

**Uploader:** complete the bundle as normal. It stays in **Pending approval**. Tell your reviewer it is waiting. If **denied**, read the reason, edit the bundle, and resubmit. Denied bundles are deleted after about 14 days (default).

**Reviewer:** open **Approval queue** from the account menu. Check title, description, files (you can download them to inspect) and recipients. Then **Approve** or **Deny** (a reason is required for denial). Confirm that the content has no unapproved prices, discounts, trade-in values, financing or warranty statements, and no internal-only information.

Check **Account** to see whether approval applies to you.

## 7. Rules of thumb

- Invitation mode + OTP is the default. Do not bypass it for convenience.
- One bundle per customer or per transaction. Do not mix customers in one bundle.
- Never share another staff member's login or invitation links.
- Never send dealer cost, margins, or John Deere program details to customers.
- Do not make commitments (price, delivery dates, warranty) in the title or description unless a human with authority has approved the wording.
- Keep emails to customers short and friendly. Say what was sent, when it expires, and who to call.

## 8. Sample customer heads-up message (edit before sending)

> Hi [CUSTOMER FIRST NAME],
>
> I've sent you a secure link by email from [DEALERSHIP NAME] with [DESCRIPTION OF FILES]. It expires on [DATE]. If you're asked for a verification code, it will arrive by email within a minute or two. Please check your junk folder if you don't see it.
>
> Any trouble, call me at [PHONE].
>
> [YOUR NAME]

## 9. Troubleshooting and escalation

| Problem | Contact |
|---|---|
| Cannot sign in, cannot upload, need higher limits, group or approval settings | IT (via [INSERT: monday.com / Freshservice / email]) |
| Possible wrong-recipient or privacy incident | Your manager and IT immediately |
| Content questions (pricing, warranty, financing) | Sales Manager / Service Manager |

Built-in help is available in the application at `/help` (Getting started, Sharing and recipients, Approval, FAQ).

## 10. Quick checklist

1. Sign in. **Create new upload**.
2. Title, description, expiry, password (if sensitive), **Invitation** mode, customer email, OTP on.
3. Upload files. Check list.
4. Double-check recipient and content. **Complete**.
5. Wait for approval if required.
6. Give the customer a heads-up (password by phone, not email).
7. Log it in ClientLink / Salesforce.
8. Resend or revoke if anything goes wrong.
