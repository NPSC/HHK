# Guest Census Report — Notes

Branch: `guestCensusReport_exp`. Custom report requested by Anayat House; the original report was written by Will Ireland.

Last updated: 2026-10-03

## Files

| File | Role |
|---|---|
| `house/GuestCensusReport.php` | Page: builds the report and handles the Run Here / Excel buttons |
| `classes/House/Report/GuestCensusReport.php` | Report class (extends `AbstractReport`) |
| `classes/House/Report/AbstractReport.php` | Shared report base; gained opt-in totals and print hooks for this report |
| `classes/ExcelHelper.php`, `classes/ExcelRichText.php` | Excel cells with red and black text runs |
| `classes/House/ResourceView.php`, `classes/House/Room/Room.php`, `classes/Tables/House/RoomRS.php` | Room Sleeping Spaces in Resource Builder |

## SQL changes

Existing sites get these from `patch/patchSQL.sql`; new installs get them from `sql/` and `install/initialdata.sql`.

### 1. New column `room.Sleeping_Spaces`

```sql
-- patch/patchSQL.sql
ALTER TABLE `room` ADD COLUMN IF NOT EXISTS `Sleeping_Spaces` INT(11) NOT NULL DEFAULT 0 AFTER `Min_Occupants`;
```

- Also added to the `room` definition in `sql/CreateAllTables.sql`, in the same position.
- It is the number of beds a room has, used for the report's bednight percentage. The existing `Beds_*` columns weren't used: no screen writes them and nothing reads them.
- Every room starts at 0 until someone sets it in Resource Builder.
- The column is added on **every** site, even though only one house uses it. Only the UI is optional (see 2). This follows HHK's existing pattern: `room.Key_Deposit_Code` exists everywhere but only shows when `KeyDeposit` is on. Making the column itself optional would break the queries that name it.

### 2. New setting `showSleepingSpaces`

```sql
-- patch/patchSQL.sql
INSERT IGNORE INTO `sys_config`(`Key`,`Value`,`Type`,`Category`,`Description`,`Show`) VALUES ("showSleepingSpaces", "false","b","h","Show room Sleeping Spaces in Resource Builder and the Guest Census Report","1");
```

- The same row is in `install/initialdata.sql`.
- `INSERT IGNORE` makes it safe to re-run: `Key` is the primary key, so a site that has already turned it on is not reset.
- `Type` `b` means boolean, `Category` `h` puts it in the House section, and `Show` `1` lists it on the Site Configuration page.
- Off by default. When off:
  - Resource Builder hides the Sleeping Spaces column and the room editor field.
  - The report summary leaves out "Percentage of Bednights".
- A site that hasn't been patched yet has no row, which reads as off.
- Settings are copied into the session at login, so log out and back in after changing it.

### 3. Report and Import pages

```sql
-- patch/patchSQL.sql
call `new_webpage`('GuestCensusReport.php',31,'Guest Census Report',1,'h','102','u','p','','',now(),'ga');
INSERT IGNORE INTO `page_securitygroup` (`idPage`,`Group_Code`)
select `idPage`, 'gr' from `page` where `File_Name` = 'GuestCensusReport.php';
call `new_webpage`('Import.php',2,'Import',1,'a','34','l','p','','',now(),'db');
```

- The Guest Census Report is a house-site page under the Reports menu. It is **hidden by default** (`Hide` = 1) and open to the Guest Admin (`ga`) and Guest Reports (`gr`) groups.
- The Import page is an admin-site page for the DB Maintenance group (`db`), also hidden by default.
- `install/initialdata.sql` adds both with fixed ids 141 and 142.
- **Watch:** if `main` adds pages with ids 141 or 142 before this branch merges, those ids will collide.

### 4. `new_webpage` no longer overwrites existing pages

- In `sql/CreateAllRoutines.sql`, when a page already exists, the procedure now just returns its id. It used to update its title, menu position, `Hide` flag and so on.
- This stops the patch from un-hiding a page a house has hidden, or moving it.
- **Watch:** this applies to every `new_webpage` call. Any patch that relied on it to change an existing page's settings won't do that any more.

The file name is case-sensitive. The page row must say `GuestCensusReport.php`. One dev database had a stale `guestCensusReport.php` row, which caused "The webpage filename was not found in our database"; that was fixed by hand and needed no repo change.

## How the report works

### What a row is

The report has one row per calendar night in the chosen period (the Dates, Month, Calendar Year or Year to Date filter). Occupancy comes from actual `stays` and `visit` records, meaning who was really checked in. Reservations are not used, so waitlisted, unconfirmed, no-show and canceled reservations never count as occupancy.

`getResultSet()` builds the rows in PHP rather than with one SQL query. `makeQuery()` is empty on purpose.

### Columns

| Column | How it is counted |
|---|---|
| Date | The night |
| Checked In / Checked Out | Distinct (visit, guest) pairs with `Checkin_Date` / `Checkout_Date` on that day |
| Rooms Occupied | Rooms with at least one stay that night |
| People in House | Guests staying that night |
| Rooms Paid / Rooms Unpaid | Occupied rooms where that night is paid / not paid (see below) |
| People Paid for Lodging | Guests in rooms whose night is paid |
| Guest Roster | One entry per room: the visit's primary guest's last name and party size, e.g. `Smith (3)`. Red when that night is unpaid, black when paid |
| Canceled: *code* | Guests on reservations that changed to that cancel status on that day |
| Bednights Canceled: *code* | Those guests × the reservation's planned nights |

- The cancel columns are generated from `ReservStatus` lookups of the Cancelled type, such as Turn Away, Guest Canceled and No Show.
- Cancellations are dated by when the status change was logged (`reservation_log`), not by the planned arrival.
- Guest counts and nights come from the reservation as it is now, because the log only records the status change.

### Stays that are still open

A stay that hasn't checked out ends on its expected checkout date. If the guest is past that date and still checked in, the stay runs through tonight.

- **Open question:** a guest due out *today* who is still here doesn't count tonight. HHK's `datedefaultnow()` doesn't count either case. Ask Will which rule he wants.

### Paid nights

This is in `getPaidThruDates()`. Invoices are only cut for the amount actually paid, so the real charge comes from the price model.

1. For each visit, total the paid lodging. This means lodging, reversal, waive and discount lines on Paid or Carried invoices, leaving out invoices sold to the subsidy. Waives and discounts are negative, so they net out the share the house covers.
2. `VisitCharges::sumCurrentRoomCharge()` converts that amount into a number of nights.
3. Those nights are counted from the first night of the visit. Every night before the resulting date is paid, and a partly paid night is unpaid.
4. Visits with no payment skip the price model and are unpaid.

This follows the Visit Interval Report's definition of paid.

### Totals and summary

- **Totals row:** sums every column except Date and Guest Roster. It appears on screen (table footer), in print, and in Excel as a bold row labeled in the roster column.
- **Summary box**, from the totals:
  - Number of Guests Canceled
  - Percentage of Rooms Unpaid = unpaid room nights / occupied room nights
  - Percentage of Bednights = people in house / (nights × total Sleeping Spaces). Only shown when `showSleepingSpaces` is on. Counts rooms not retired before the period starts.
  - Average Length of Stay = people in house / checked in, to 2 decimals
- Excel gets the summary on its own Summary sheet.

### Output formats

| Output | Guest Roster | Mechanism |
|---|---|---|
| Screen | Bootstrap pills linking to Guest Edit; red when unpaid, grey when paid | HTML in the cell |
| Print | `Smith (3)` in red/bold or black | The roster cell carries a `data-print` attribute with plain colored markup. `AbstractReport` prints that instead (`$printKeepHtml`), and prints the footer (`$printFooter`) |
| Excel | `Smith (3)` in red or black | `ExcelRichText` cell; the `ExcelHelper::writeCell()` override writes colored text runs |
| PDF | Browser print to PDF | Not verified with Will |

## Names are stored HTML-encoded (fixed)

- **The issue:** HHK stores last names HTML-encoded. `IndivMember` saves `O'Brien` as `O&#039;Brien`.
- **The standard HHK pattern:**
  - Screens print the stored value as-is, and the browser shows `O'Brien`.
  - Excel output goes through `ExcelHelper::convertStrings()`, which decodes it.
- **What went wrong here:**
  - On screen, this report called `htmlspecialchars()` on the already-encoded name. That escaped it twice, so users saw `O&#039;Brien`. This predates the paid-night commit.
  - In Excel, the colored roster cells skip `convertStrings()`, so nothing decoded the name and it was encoded again.
- **The fix follows the standard pattern** (decided 2026-10-03: follow HHK patterns wherever possible):
  - The report keeps the stored value and puts it into the screen pill, the tooltip and the print markup as-is, with no `htmlspecialchars()`.
  - The one escape left is the outer `htmlspecialchars(..., ENT_QUOTES)` on `data-print`. It escapes the print markup into an attribute, not the name.
  - `ExcelHelper::convertStrings()` now decodes `ExcelRichText` cells too, applying the new `ExcelHelper::convertString()` to each colored run through `ExcelRichText::mapText()`. Before, it skipped them.
  - The report therefore needs no Excel-specific decoding, and plain-string cells in other reports behave exactly as before.
- **Tested** with a stored name of `O'Price&<i>"`:
  - The page source carries the stored value unchanged.
  - Screen, tooltip and print show `O'Price&<i>"`.
  - Excel shows `O'Price&"`, because `convertStrings()` strips tags. That is what every HHK report's Excel output does.
- A first version decoded the name when it was read, then escaped it for each output. It produced the same display but wasn't the HHK pattern, so it was replaced.

## Open items

From the review in `Roast.md`, checked against the code.

**Confirmed, to do:**
- **Carried invoices count as paid** even when the invoice they were carried to is unpaid. Decision needed: fix only this report, or this report and the Visit Interval Report (which has the same bug), or keep matching that report. The suggested fix is to use `VisitCharges::sumPayments()`, which follows chains of carried invoices.
- **Error handling:** wrap each visit's price-model call in try/catch and show a warning, so one bad visit can't break the whole report.
- **Clamp Sleeping Spaces** to 0 or more. Today `-4` saves as −4.
- **Small items:**
  - "Guests" in the summary is hard-coded instead of using the guest label.
  - The Excel totals row has no label when Guest Roster isn't selected.
  - Excel's AutoFilter can sort the totals row in among the data rows.
  - `writeExcelFooter()` has an unused `$hdr` parameter.

**Separate fix:**
- `house/ws_resc.php` `redit` and `rdel` have no Guest Admin check. Groups `g`, `gr`, `h` and `ro` can edit rooms. This predates the branch and needs Will's agreement.

**Questions for Will:**
- The overdue-guest rule (see "Stays that are still open").
- Should guests on leave count as in the house?
- Should future nights count for guests who are currently in house?
- Is browser print to PDF acceptable?

**Not yet measured:** the speed of a 12-month range on a realistic database. The price model runs once per visit with any payment, and dev data has too few paid visits to tell.

**Known approximations:**
- A room retired partway through the period counts for the whole period's sleeping spaces.
- Paid nights are always counted from the first night of the visit.
