# UNIVERSIDAD DE DAGUPAN

**SCHOOL OF INFORMATION TECHNOLOGY EDUCATION**  
Arellano Street, Dagupan City, Pangasinan

# BETA TEST EVALUATION FORM — GastoTrack

**Tester / evaluator:** ____________________  **Date:** ____________________  
**Business / test environment:** ____________________  **Build / commit / APK version:** ____________________  
**Device and Android version:** ____________________  **Browser:** ____________________

This form covers the currently implemented GastoTrack web and staff Android workflows. Confirm the included functions against the **approved Scope and Limitations** before testing. Checkboxes are intentionally blank; record only results observed during the beta test. Use `N/A` for a function excluded by the approved scope.

## II. PRE-TEST REQUIREMENTS

Mark each item after verification. Testing should begin only when all applicable requirements are available.

| Verified | Requirement | Verification / attachment |
| --- | --- | --- |
| □ | The developed system conforms to the approved scope and limitations. | Compare this test list with the signed/approved scope; record reviewer and date: ____________________ |
| □ | A current copy of the approved Scope and Limitations is attached. | Attachment name / version: ____________________ |
| □ | A current system flowchart or process flow is attached. | Candidate: `docs/GastoTrack_System_Diagrams.md`; confirm it is current and attach/export it. |
| □ | Test accounts, sample data, and required devices or software are ready. | Prepare Super Admin, Owner, and Staff test accounts; an approved test business; products, recipes, stock, and transactions; Android phone/APK; browser; network on/off; and test email access. Record details below. |

**Test account identifiers (no passwords):** Super Admin __________ Owner __________ Staff __________  
**Test business and data set:** ____________________  **API / web URL:** ____________________  
**Evidence folder or link:** ____________________

## III. TEST ACTIVITIES

Record completion and the result of each activity. Use the remarks column for defects, evidence, or follow-up actions. The times below are targets from the supplied form.

| Activity | Target time | Result | Remarks / evidence |
| --- | --- | --- | --- |
| Code Review | 5 minutes | □ Pass □ Failed | Review the release/build, role access, and affected modules. Reviewer/date: ____________________ |
| Debugging | 5 minutes | □ Pass □ Failed | Reproduce and record defects; list issue numbers and fixes verified: ____________________ |
| Beta Challenge | 10 minutes–24 hours | □ Pass □ Failed | Run the role-based cases below on the intended web and Android builds. Session/date/evidence: ____________________ |

## IV. FUNCTIONS BY USER ROLE

This is a candidate list from the current system. Remove or mark `N/A` for anything outside the approved scope; add any approved function that is missing.

| User role | Function / module | Tested |
| --- | --- | --- |
| Visitor | View landing page and submit Contact message | □ Yes □ No |
| Business Owner | Register, verify email, and view approval status | □ Yes □ No |
| Business Owner | Sign in and view dashboard | □ Yes □ No |
| Business Owner | Manage staff accounts | □ Yes □ No |
| Business Owner | Manage products, prices, sizes, and ingredient recipes | □ Yes □ No |
| Business Owner | Review and accept/reject staff product edit requests | □ Yes □ No |
| Business Owner | Create and edit stock items, units, cost, minimum quantity, and adjustments | □ Yes □ No |
| Business Owner | View orders, transactions, analytics, reports, and staff activity | □ Yes □ No |
| Staff | Sign in, use MPIN, and view the product catalog | □ Yes □ No |
| Staff | Create orders, confirm payments, and view order history | □ Yes □ No |
| Staff | Record income/expenses and scan receipts | □ Yes □ No |
| Staff | View and adjust stock; request product edits | □ Yes □ No |
| Staff | Save supported work offline and sync it when connected | □ Yes □ No |
| Staff | View profile, permissions, and submit bug reports | □ Yes □ No |
| Super Admin | Review and approve/suspend business registrations | □ Yes □ No |
| Super Admin | Manage subscriptions, users, and password reset requests | □ Yes □ No |
| Super Admin | View activity logs, bug reports, and platform reports | □ Yes □ No |

## V. FUNCTIONAL TEST LOG

For each case, record the actual result, evidence (such as screenshot or record ID), and issue number in the last column. `P` = Pass, `F` = Failed, `N/A` = outside approved scope or not applicable to this run. Do not mark `P` without observing the expected result.

| Role | Function / test case | Expected result | Status | Finding / issue no. |
| --- | --- | --- | --- | --- |
| Visitor | Open the landing page and submit a valid Contact message without signing in. | Submission succeeds; the configured admin inbox receives the message and the sender receives the acknowledgement email. | □ P □ F □ N/A | ____________________ |
| Owner | Register a test business and verify its email address. | Verification uses the registering account's address; the business shows its correct approval status. | □ P □ F □ N/A | ____________________ |
| Super Admin | Approve a pending test business. | Status changes to approved; its owner can enter the permitted workspace. | □ P □ F □ N/A | ____________________ |
| Owner | Sign in and open the dashboard. | The owner sees only the selected business's data and available modules. | □ P □ F □ N/A | ____________________ |
| Owner | Add a staff account and sign in on Android with that account. | The staff account can sign in to its business; another business's data is not shown. | □ P □ F □ N/A | ____________________ |
| Staff | Set an MPIN, leave/reopen the app, and enter the MPIN. | The correct MPIN unlocks the previously signed-in account; an incorrect MPIN does not. | □ P □ F □ N/A | ____________________ |
| Owner | Create a product with a price, size, and per-item ingredient recipe. | The product and recipe save and appear to staff after refresh. | □ P □ F □ N/A | ____________________ |
| Staff / Owner | Submit a product edit request, then approve it as owner. | The request changes status and the approved product values appear on owner web and staff Android after refresh. | □ P □ F □ N/A | ____________________ |
| Owner / Staff | Edit a stock item's unit, unit cost, and minimum quantity; refresh the staff stock screen. | New values save and staff sees the updated stock details after refresh. | □ P □ F □ N/A | ____________________ |
| Staff / Owner | Sell two units of a recipe product; compare stock before and after. | Each ingredient falls by `recipe quantity per product × 2`; the owner sees the order and resulting stock movement. | □ P □ F □ N/A | ____________________ |
| Staff | Attempt an order when an ingredient has insufficient stock. | The order is rejected or remains unresolved; no negative stock or duplicate confirmed sale is created. | □ P □ F □ N/A | ____________________ |
| Staff / Owner | Record an income/expense entry as staff; inspect owner transactions/report. | The entry appears once, with the correct amount, type, business, and staff attribution. | □ P □ F □ N/A | ____________________ |
| Staff / Owner | Adjust loaded stock as staff; inspect owner inventory and activity. | Quantity and movement history reflect the adjustment once, with the staff actor. | □ P □ F □ N/A | ____________________ |
| Staff / Owner | With the app previously signed in, disconnect the network, save a cash order, reconnect, and pull down to sync. | The order is labelled pending locally, then appears once in owner records after server acceptance; stock deducts only after acceptance. | □ P □ F □ N/A | ____________________ |
| Staff | Save a supported offline transaction and stock adjustment; reconnect and sync. | Each accepted action reaches the server once; rejected actions stay visible for review. | □ P □ F □ N/A | ____________________ |
| Staff | Scan a sample receipt and review extracted details before saving. | OCR presents editable details; the saved transaction matches the reviewed values when online. | □ P □ F □ N/A | ____________________ |
| Owner | Open order, sales, inventory, and staff activity reports. | Report figures and staff actions match the test records and selected date/business. | □ P □ F □ N/A | ____________________ |
| Staff / Super Admin | Submit a bug report from Android and review it in the admin console. | Report is recorded for the correct business and visible to authorized administrators. | □ P □ F □ N/A | ____________________ |
| Super Admin | Suspend a test business, then try owner and staff actions. | Access follows the approved suspension rules; actions resume only after reactivation. | □ P □ F □ N/A | ____________________ |

**Additional failed or partially completed cases / continuation sheet:** ____________________  
**Issues recorded:** ____________________  **Retest date and result:** ____________________

**Tester signature:** ____________________  **Evaluator signature:** ____________________  **Date:** ____________________
