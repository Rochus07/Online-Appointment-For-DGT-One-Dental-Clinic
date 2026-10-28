<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['admin']);
$PAGE_TITLE = 'User Accounts';
$ACTIVE = 'users';
$PAGE_FULL = true;   // admin screens fill the full viewport width

$btnBase    = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:cursor-not-allowed disabled:opacity-50';
$btnPrimary = "$btnBase bg-accent text-white hover:bg-accent-dark";
$btnGhost   = "$btnBase border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5";
$inputCls   = 'rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
$tableCls   = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 '
    . '[&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5 [&_.amt]:text-right';

ob_start();
?>
<style>
  /* Kill the native datalist dropdown arrow on the Degree typeahead. */
  #u_degree::-webkit-calendar-picker-indicator { display: none !important; }
  #u_degree::-webkit-list-button { display: none !important; }
</style>
<div class="rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800">
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <!-- Which accounts to show (was a row of tab buttons, 2026-10-18). -->
        <select id="userRole" aria-label="Account type" class="<?= $inputCls ?> min-w-[190px] cursor-pointer py-[9px] font-semibold">
            <option value="dentist" selected>Doctors</option>
            <option value="dental_assistant">Assistant Dentists</option>
            <option value="receptionist">Receptionists</option>
            <option value="cashier">Cashiers</option>
            <option value="patient">Patients</option>
            <option value="admin">Admins</option>
        </select>
        <button class="<?= $btnPrimary ?>" id="addUserBtn">Add Doctor</button>
        <button class="<?= $btnGhost ?>" id="archiveBtn">View Archive</button>
    </div>
    <div class="mb-3.5 flex flex-wrap items-center gap-3">
        <input type="search" id="userSearch" placeholder="Search by name, email or contact&hellip;"
               class="<?= $inputCls ?> min-w-[220px] flex-1">
    </div>
    <div class="overflow-x-auto">
        <table class="<?= $tableCls ?>" id="userTable">
            <thead><tr id="userHead"></tr></thead>
            <tbody><tr><td class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="userPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="User pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const val = (id) => (g(id) ? g(id).value.trim() : '');
  let role = 'dentist';
  let cache = [];
  const PG = { page: 1 };   // pager (window.pageSlice + fitRows)
  let SPECS = {};

  const BTN     = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:cursor-not-allowed disabled:opacity-50';
  const BTN_SM  = 'inline-flex shrink-0 whitespace-nowrap cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold disabled:opacity-50';
  const PRIMARY = ' bg-accent text-white hover:bg-accent-dark';
  const GHOST   = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const WARN    = ' bg-warn text-white hover:brightness-95';
  const OK      = ' bg-ok text-white hover:brightness-95';
  const FG      = 'flex flex-col gap-1.5';
  const LBL     = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
  const REQ     = '<span class="text-danger"> *</span>';   // required-field marker
  const INP     = 'rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
  const HINT    = 'text-[11px] text-steel-400 dark:text-steel-500';
  const BADGE   = 'inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const AVATAR  = 'grid h-[26px] w-[26px] place-items-center overflow-hidden rounded-full bg-navy-700 text-xs font-bold text-white [&_img]:h-full [&_img]:w-full [&_img]:object-cover';

  const ROLES = {
    dentist:          { label: 'Doctor',           plural: 'Doctors' },
    dental_assistant: { label: 'Assistant Dentist', plural: 'Assistant Dentists' },
    receptionist:     { label: 'Receptionist',     plural: 'Receptionists' },
    cashier:          { label: 'Cashier',          plural: 'Cashiers' },
    patient:          { label: 'Patient',          plural: 'Patients' },
    admin:            { label: 'Admin',            plural: 'Admins' },
  };

  // Role Type dropdown used in the Add / Edit pop-ups (2026-10-18).
  const ROLE_ORDER = ['dentist', 'dental_assistant', 'receptionist', 'cashier', 'patient', 'admin'];
  function roleField(id, sel, note) {
    return '<div class="' + FG + ' col-span-2"><label class="' + LBL + '" for="' + id + '">Role Type' + REQ + '</label>' +
      '<select class="' + INP + ' font-semibold" id="' + id + '">' +
      ROLE_ORDER.map((r) => '<option value="' + r + '"' + (r === sel ? ' selected' : '') + '>' + ROLES[r].label + '</option>').join('') +
      '</select>' + (note ? '<span class="' + HINT + '">' + note + '</span>' : '') + '</div>';
  }
  // Show another role's list (after adding or moving an account there).
  function showList(r) {
    role = r;
    if (g('userRole')) g('userRole').value = r;
    PG.page = 1;
    load();
  }

  const DOCTOR_TYPES = { owner: 'In-House Doctor', roaming: 'Roaming Doctor' };
  const DOCTOR_TYPE_SHORT = { owner: 'In-House', roaming: 'Roaming' };

  const prettyKey = (k) => k.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  const specNames = (csv) => (csv || '').split(',').filter(Boolean).map((k) => SPECS[k] || prettyKey(k)).join(', ');

  /* ---------- table shape per role ---------- */
  function headCells() {
    if (role === 'dentist') return ['Avatar', 'Name', 'PRC License', 'Specialty', 'Email', 'Experience', 'Status', 'Actions'];
    if (role === 'admin') return ['Avatar', 'Name', 'Username', 'Email', 'Status', 'Actions'];
    if (role === 'patient') return ['Avatar', 'Name', 'Age', 'Email', 'Phone', 'Registration Date', 'Status', 'Actions'];
    return ['Avatar', 'Name', 'Email', 'Contact', 'Experience', 'Status', 'Actions'];
  }

  // "2025-11-22 14:59:38" -> "2025-11-22 2:59:38 PM" (12-hour, seconds kept).
  function fmtDateTime(s) {
    const m = String(s || '').match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
    if (!m) return s || 'N/A';
    let h = +m[2];
    const ap = h < 12 ? 'AM' : 'PM';
    h = h % 12 || 12;
    return m[1] + ' ' + h + ':' + m[3] + ':' + m[4] + ' ' + ap;
  }

  // Live from the users row — reflects any profile-photo change on the next load.
  function avatarCell(u) {
    const src = u.avatar ? '/dgt_dental_clinic/uploads/avatars/' + encodeURIComponent(u.avatar) : '';
    const initial = esc((u.full_name || '?').trim().charAt(0).toUpperCase() || '?');
    return '<td><span class="' + AVATAR + '">' + (src ? '<img src="' + esc(src) + '" alt="">' : initial) + '</span></td>';
  }

  function bodyCells(u) {
    const name = avatarCell(u) + '<td><strong>' + esc(u.full_name) + '</strong></td>';
    const exp = '<td>' + (u.years_experience != null ? esc(u.years_experience) + ' years' : 'N/A') + '</td>';
    const status = '<td><span class="' + BADGE + ' bg-ok-soft text-ok dark:bg-ok/20">Active</span></td>';
    // Dental assistants: user_id here is the roster id; login_user_id is the
    // linked account (migration 058). A roster row with no login yet gets one
    // the next time its profile is saved, so Reset Password only shows once linked.
    if (role === 'dental_assistant') {
      const act = '<td><div class="flex flex-nowrap items-center gap-2">' +
        (u.login_user_id ? '<button class="' + BTN_SM + GHOST + '" data-reset="' + u.login_user_id + '">Reset Password</button>' : '') +
        '<button class="' + BTN_SM + WARN + '" data-arch="' + u.user_id + '">Archive</button></div></td>';
      return name +
        '<td>' + esc(u.email || 'N/A') + '</td><td>' + esc(u.contact_number || 'N/A') + '</td>' + exp + status + act;
    }
    // Admins: nobody archives the main (first) admin, and no one archives themselves.
    const uid = +u.user_id, noArch = role === 'admin' && (uid === ME_ID || uid === OWNER_ID);
    const actions = '<td><div class="flex flex-nowrap items-center gap-2">' +
      '<button class="' + BTN_SM + GHOST + '" data-reset="' + u.user_id + '">Reset Password</button>' +
      (noArch ? '' : '<button class="' + BTN_SM + WARN + '" data-arch="' + u.user_id + '">Archive</button>') +
      '</div></td>';
    if (role === 'dentist') {
      // Duty mode folds into the Status badge itself ("Active Roaming" /
      // "Active In-House") — there is no separate Type column any more.
      const dtStatus = '<td><span class="' + BADGE + ' bg-ok-soft text-ok dark:bg-ok/20">Active ' + esc(DOCTOR_TYPE_SHORT[u.doctor_type] || 'Roaming') + '</span></td>';
      const prc = '<td>' + esc(u.prc_license || 'N/A') + '</td>';
      return name + prc + '<td>' + esc(specNames(u.specialties) || 'N/A') + '</td><td>' + esc(u.email || 'N/A') + '</td>' + exp + dtStatus + actions;
    }
    if (role === 'admin') {
      return name + '<td>' + esc(u.username) + '</td><td>' + esc(u.email || 'N/A') + '</td>' + status + actions;
    }
    if (role === 'patient') {
      // Age is computed server-side from patient_records.date_of_birth.
      return name + '<td>' + (u.age != null && u.age !== '' ? esc(u.age) : 'N/A') + '</td>' +
        '<td>' + esc(u.email || 'N/A') + '</td><td>' + esc(u.contact_number || 'N/A') + '</td>' +
        '<td>' + esc(fmtDateTime(u.created_at)) + '</td>' + status + actions;
    }
    return name + '<td>' + esc(u.email || 'N/A') + '</td><td>' + esc(u.contact_number || 'N/A') + '</td>' + exp + status + actions;
  }

  let ME_ID = 0, OWNER_ID = 0;
  async function load() {
    try {
      if (role === 'dental_assistant') {
        const res = await api('assistants.php?action=list');
        cache = (res.assistants || []).map((a) => ({
          user_id: a.assistant_id, login_user_id: a.user_id ? +a.user_id : null, username: a.username || '',
          full_name: a.full_name, is_active: +a.is_active, avatar: a.avatar,
          first_name: a.first_name, last_name: a.last_name,
          email: a.email, contact_number: a.contact_number, years_experience: a.years_experience, about: a.about,
          created_at: a.created_at, date_of_birth: a.date_of_birth,
        }));
        SPECS = {};
      } else {
        const res = await api('users.php?action=list&role=' + role);
        cache = res.users;
        SPECS = res.specialties || {};
        ME_ID = +res.me_id || 0; OWNER_ID = +res.owner_admin_id || 0;
      }
      g('addUserBtn').textContent = role === 'dental_assistant' ? 'Add Assistant Dentists' : 'Add ' + ROLES[role].label;
      const archived = cache.filter((u) => u.is_active == 0).length;
      g('archiveBtn').textContent = 'View Archive' + (archived ? ' (' + archived + ')' : '');
      render();
    } catch (e) {
      g('userTable').querySelector('tbody').innerHTML = '<tr><td class="py-7 text-center text-steel-400 dark:text-steel-500">' + esc(e.message) + '</td></tr>';
    }
  }

  function render() {
    const cols = headCells();
    g('userHead').innerHTML = cols.map((c) => '<th>' + c + '</th>').join('');
    const q = val('userSearch').toLowerCase();
    const match = cache.filter((u) => u.is_active == 1 &&
      (!q || (u.full_name + ' ' + (u.email || '') + ' ' + (u.contact_number || '')).toLowerCase().includes(q)));
    const { rows } = window.pageSlice(match, PG, window.fitRows(g('userTable')), g('userPager'), render);
    const tb = g('userTable').querySelector('tbody');
    tb.innerHTML = rows.length
      // data-edituser makes the row itself open that person's editable profile
      // (the userTable click handler lets any action button inside — Reset
      // Password, Archive — take priority and return first).
      ? rows.map((u) => '<tr class="cursor-pointer" data-edituser="' + u.user_id + '" title="Click to edit">' + bodyCells(u) + '</tr>').join('')
      : '<tr><td colspan="' + cols.length + '" class="py-7 text-center text-steel-400 dark:text-steel-500">No active ' + ROLES[role].plural + '.</td></tr>';
  }

  /* ---------- add form per role ---------- */
  // The temporary password is generated by the system on save — no field here.
  function pwdFields() {
    return '<div class="' + FG + ' col-span-2 rounded-md border border-steel-200 bg-steel-50 px-3 py-2.5 dark:border-white/15 dark:bg-white/5">' +
      '<span class="' + HINT + '">A one-time temporary password is generated automatically. It is shown once after you save. Copy it and give it to the account holder, who must set their own password at first login.</span></div>';
  }

  function nameFields() {
    return '<div class="' + FG + '"><label class="' + LBL + '">First Name' + REQ + '</label><input class="' + INP + '" id="u_first"></div>' +
            '<div class="' + FG + '"><label class="' + LBL + '">Last Name' + REQ + '</label><input class="' + INP + '" id="u_last"></div>';
  }

  function formBody(role) {
    if (role === 'dental_assistant') {
      // Roster profile + a login account (migration 058). Same field layout as
      // receptionist/cashier; the account itself is created by assistants.php.
      return nameFields() +
        '<div class="' + FG + ' col-span-2"><span class="' + HINT + '">The username is generated automatically as <strong>first.last</strong>.</span></div>' +
        dobField('u_dob', '', STAFF_MIN_AGE) +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Email Address' + REQ + '</label><input class="' + INP + '" id="u_email" type="email"></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Phone Number' + REQ + '</label><input class="' + INP + '" id="u_phone" type="tel" inputmode="numeric"></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Years of Experience</label><input class="' + INP + '" id="u_years" type="number" min="0"></div>' +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">About</label><textarea class="' + INP + ' min-h-[72px] resize-y" id="u_about" placeholder="Background and skills…"></textarea></div>' +
        pwdFields();
    }
    const common = nameFields() +
      '<div class="' + FG + ' col-span-2"><span class="' + HINT + '">The username is generated automatically as <strong>first.last</strong>.</span></div>';
    if (role === 'admin') {
      return common +
        dobField('u_dob', '', STAFF_MIN_AGE) +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Email Address' + REQ + '</label><input class="' + INP + '" id="u_email" type="email"></div>' +
        pwdFields();
    }
    if (role === 'dentist') {
      // Ordered identity -> credentials -> bio. Does not use `common` (Last Name
      // pairs with Email here, not full-width).
      // Degree suggestions: the standard degrees + any already used by a dentist,
      // so a one-off degree entered once shows up in the list next time.
      const DEGREE_PRESETS = ['Doctor of Dental Medicine', 'Doctor of Dental Surgery', 'Bachelor of Dental Surgery', 'Master of Science in Dentistry'];
      const degreeOpts = [...new Set([...DEGREE_PRESETS, ...cache.map((u) => (u.degree || '').trim()).filter(Boolean)])];
      return '' +
        '<div class="' + FG + '"><label class="' + LBL + '">First Name' + REQ + '</label><input class="' + INP + '" id="u_first"></div>' +
                '<div class="' + FG + '"><label class="' + LBL + '">Last Name' + REQ + '</label><input class="' + INP + '" id="u_last"></div>' +
        dobField('u_dob', '', STAFF_MIN_AGE) +
        '<div class="' + FG + '"><label class="' + LBL + '">Email Address' + REQ + '</label><input class="' + INP + '" id="u_email" type="email"></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Degree' + REQ + '</label><input class="' + INP + '" id="u_degree" list="u_degreeList" autocomplete="off">' +
          '<datalist id="u_degreeList">' + degreeOpts.map((d) => '<option value="' + esc(d) + '"></option>').join('') + '</datalist></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">PRC License No.' + REQ + '</label><input class="' + INP + '" id="u_prc" inputmode="numeric" maxlength="7" placeholder="7-digit number">' +
          '<div class="field-err" id="u_prcErr" hidden></div></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Doctor Type</label><select class="' + INP + '" id="u_doctype">' +
          '<option value="owner">' + DOCTOR_TYPES.owner + '</option>' +
          '<option value="roaming" selected>' + DOCTOR_TYPES.roaming + '</option>' +
        '</select></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Years of Experience</label><input class="' + INP + '" id="u_years" type="number" min="0"></div>' +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Specialties' + REQ + ' <span class="text-steel-400 dark:text-steel-500">(select at least one)</span></label><div class="grid grid-cols-2 gap-2" id="u_specList">' +
          Object.entries(SPECS).map(([k, l]) => '<label class="flex items-center gap-2 font-medium"><input type="checkbox" class="u_spec accent-accent" value="' + k + '"> ' + esc(l) + '</label>').join('') +
        '</div></div>' +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">About the Doctor</label><textarea class="' + INP + ' min-h-[72px] resize-y" id="u_about" placeholder="Background and expertise…"></textarea></div>' +
        pwdFields();
    }
    if (role === 'patient') {
      // Contact Number pairs with Email so neither sits alone on a row.
      return common +
        dobField('u_dob', '') +
        '<div class="' + FG + '"><label class="' + LBL + '">Phone Number' + REQ + '</label><input class="' + INP + '" id="u_phone" type="tel" inputmode="numeric"></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Email Address' + REQ + '</label><input class="' + INP + '" id="u_email" type="email"></div>' +
        pwdFields();
    }
    // receptionist / cashier — Email full-width, then
    // Phone pairs with Years of Experience so no field is left on a half row.
    return common +
      dobField('u_dob', '', STAFF_MIN_AGE) +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Email Address' + REQ + '</label><input class="' + INP + '" id="u_email" type="email"></div>' +
      '<div class="' + FG + '"><label class="' + LBL + '">Phone Number' + REQ + '</label><input class="' + INP + '" id="u_phone" type="tel" inputmode="numeric"></div>' +
      '<div class="' + FG + '"><label class="' + LBL + '">Years of Experience</label><input class="' + INP + '" id="u_years" type="number" min="0"></div>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">About</label><textarea class="' + INP + ' min-h-[72px] resize-y" id="u_about" placeholder="Background and skills…"></textarea></div>' +
      pwdFields();
  }

  // Date of Birth (age is always computed from it, never stored). Every new
  // account holder must be 18 to 80; the Year list only covers that range. A
  // birthdate already on file shows as the locked Age, so the range only
  // applies when the field is empty. req = red star.
  const STAFF_MIN_AGE = 18, ACCOUNT_MAX_AGE = 80;
  function dobField(id, value, minAge, req) {
    return '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Date of Birth' + REQ + '</label>' +
      dobSelectsHtml(id, value, INP, value ? { swap: true } : { swap: true, minAge: STAFF_MIN_AGE, maxAge: ACCOUNT_MAX_AGE }) +
      '<span class="' + HINT + '" id="' + id + '_age"></span></div>';
  }
  function ageLine(dob) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dob || '')) return '';
    const b = new Date(dob + 'T00:00:00'), n = new Date();
    let a = n.getFullYear() - b.getFullYear();
    if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) a--;
    return a >= 0 ? 'Age: ' + a : '';
  }
  function wireDob(id) {
    const el = g(id);
    if (!el) return;
    wireDobSelects(id);
    // The "Age: N" hint is only for while the dropdowns are showing; a saved
    // birthdate already shows as the Age box.
    const hint = () => { g(id + '_age').textContent = dobShowsAge(id) ? '' : ageLine(el.value); };
    el.addEventListener('input', hint);
    hint();
  }
  function dobOk(v) { return /^\d{4}-\d{2}-\d{2}$/.test(v) && v <= todayISO(); }
  const DOB_STAFF_MSG = 'Enter the date of birth. The account holder must be ' + STAFF_MIN_AGE + ' to ' + ACCOUNT_MAX_AGE + ' years old.';

  // role = the type being added (the Role Type dropdown can switch it; what
  // was already typed is kept). keep = {first, last, email, phone}.
  function openForm(role, keep) {
    keep = keep || {};
    const addLabel = role === 'dental_assistant' ? 'Add Assistant Dentist' : 'Add New ' + ROLES[role].label;
    const saveLabel = role === 'dental_assistant' ? 'Add Assistant Dentist' : 'Add ' + ROLES[role].label;
    openModal(modalShell(addLabel,
      '<div class="grid grid-cols-2 gap-3.5">' + roleField('u_roleType', role) + formBody(role) +
      '</div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="u_save">' + saveLabel + '</button>'));
    [['u_first', keep.first], ['u_last', keep.last], ['u_email', keep.email], ['u_phone', keep.phone]]
      .forEach(([id, v]) => { if (v && g(id)) g(id).value = v; });
    g('u_roleType').addEventListener('change', (e) => {
      openForm(e.target.value, { first: val('u_first'), last: val('u_last'), email: val('u_email'), phone: val('u_phone') || keep.phone });
    });

    if (g('u_phone')) wirePhoneField(g('u_phone'), { submitBtn: g('u_save') });
    wireDob('u_dob');

    // (The mailbox-existence check / e-mailed code was removed: accounts are
    // created on the spot with a temporary password, format check only.)

    // PRC License No. — exactly 7 digits (same red-border + inline-error
    // pattern as wirePhoneField / the GCash reference-number check).
    const prcInput = g('u_prc');
    if (prcInput) {
      const prcErr = g('u_prcErr');
      const validatePrc = () => {
        prcInput.value = prcInput.value.replace(/\D+/g, '').slice(0, 7);
        const bad = prcInput.value !== '' && prcInput.value.length !== 7;
        prcInput.classList.toggle('is-invalid', bad);
        if (prcErr) { prcErr.hidden = !bad; if (bad) prcErr.textContent = 'PRC License No. must be exactly 7 digits.'; }
        if (g('u_save')) g('u_save').disabled = bad;
      };
      prcInput.addEventListener('input', validatePrc);
      prcInput.addEventListener('blur', validatePrc);
    }

    g('u_save').addEventListener('click', async () => {
      if (role === 'dental_assistant') {
        if (!val('u_first') || !val('u_last')) return toast('Enter the first and last name.', 'err');
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('u_email'))) return toast('Enter a valid email address. It is needed for password reset.', 'err');
        if (g('u_phone') && !g('u_phone')._phoneOk(false)) return toast('Enter a valid phone number. It is required. ' + PH_MOBILE_MSG, 'err');
        if (!dobOk(val('u_dob'))) return toast(DOB_STAFF_MSG, 'err');
        try {
          const res = await api('assistants.php?action=add', { method: 'POST',
            body: {
              first_name: val('u_first'), last_name: val('u_last'), date_of_birth: val('u_dob'),
              contact_number: val('u_phone'), email: val('u_email'),
              years_experience: val('u_years'), about: val('u_about'),
            } });
          showCreds(res, ROLES[role].label); toast('Assistant Dentist account created.'); showList(role);
        } catch (e) { toast(e.message, 'err'); }
        return;
      }
      if (!val('u_first') || !val('u_last')) return toast('Enter the first and last name.', 'err');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('u_email'))) return toast('Enter a valid email address. It is needed for password reset.', 'err');
      if (g('u_phone') && !g('u_phone')._phoneOk(false)) return toast('Enter a valid phone number. It is required. ' + PH_MOBILE_MSG, 'err');

      const body = {
        first_name: val('u_first'), last_name: val('u_last'),
        role, email: val('u_email'),
      };
      if (role === 'dentist') {
        body.doctor_type = val('u_doctype') || 'roaming';
        body.specialties = [...document.querySelectorAll('.u_spec:checked')].map((c) => c.value);
        body.degree = val('u_degree');
        body.prc_license = val('u_prc');
        body.years_experience = val('u_years');
        body.about = val('u_about');
        if (!body.degree) return toast('Enter the doctor’s degree.', 'err');
        if (!/^\d{7}$/.test(body.prc_license)) return toast('Enter a valid 7-digit PRC License No.', 'err');
        if (!body.specialties.length) return toast('Select at least one specialty.', 'err');
      }
      if (role === 'receptionist' || role === 'cashier') {
        body.contact_number = val('u_phone');
        body.years_experience = val('u_years');
        body.about = val('u_about');
      }
      if (role === 'patient') {
        body.contact_number = val('u_phone');
        body.date_of_birth = val('u_dob');
        if (!dobOk(body.date_of_birth)) return toast('Enter the patient’s date of birth.', 'err');
      } else {
        body.date_of_birth = val('u_dob');
        if (!dobOk(body.date_of_birth)) return toast(DOB_STAFF_MSG, 'err');
      }

      try {
        const res = await api('users.php?action=add', { method: 'POST', body });
        showCreds(res, ROLES[role].label);
        toast(ROLES[role].label + ' account created.'); showList(role);
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  // One-time sign-in details after an account is created.
  function showCreds(res, label) {
    openModal(modalShell((label || ROLES[role].label) + ' account created',
      '<p class="text-[13px] text-steel-500 dark:text-steel-300">Copy these sign-in details and give them to the account holder. The temporary password is shown only once.</p>' +
      '<div class="mt-3 grid gap-2 sm:grid-cols-2">' +
        '<div class="rounded-lg border border-steel-200 bg-steel-50 px-4 py-3 dark:border-white/15 dark:bg-white/5">' +
          '<p class="text-[11px] font-semibold uppercase tracking-wide text-steel-500 dark:text-steel-400">Username</p>' +
          '<p class="font-mono text-lg font-bold text-navy-900 dark:text-steel-50">' + esc(res.username) + '</p></div>' +
        '<div class="rounded-lg border border-steel-200 bg-steel-50 px-4 py-3 dark:border-white/15 dark:bg-white/5">' +
          '<p class="text-[11px] font-semibold uppercase tracking-wide text-steel-500 dark:text-steel-400">Temporary Password</p>' +
          '<p class="font-mono text-lg font-bold text-navy-900 dark:text-steel-50">' + esc(res.temp_password) + '</p></div>' +
      '</div>' +
      '<p class="mt-3 text-[12px] text-steel-400 dark:text-steel-500">They must set their own password the first time they sign in.</p>',
      '<button class="' + BTN + PRIMARY + '" data-close>Done</button>'));
  }

  /* ---------- edit an existing account (click the row) ---------- */
  // role = the role being edited into (the Role Type dropdown can change it);
  // fromRole = the account's current role. u.user_id is the login account id.
  function editUserModal(u, role, fromRole) {
    fromRole = fromRole || role;
    if (role === 'dental_assistant' && fromRole === 'dental_assistant') return editAssistantModal(u._roster || u);
    const isDentist = role === 'dentist';
    const lockRole = fromRole === 'admin' && (+u.user_id === ME_ID || +u.user_id === OWNER_ID);
    const moving = role !== fromRole;
    const selectedSpecs = new Set((u.specialties || '').split(',').filter(Boolean));
    let fields = (lockRole ? '' : roleField('ue_roleType', role, moving
        ? 'This account will move from ' + ROLES[fromRole].plural + ' to ' + ROLES[role].plural + '. The username, password and history stay the same.'
        : 'Pick another role to move this same account (no new account is made).')) +
      '<div class="' + FG + '"><label class="' + LBL + '">First Name' + REQ + '</label><input class="' + INP + '" id="ue_first" value="' + esc(u.first_name || '') + '"></div>' +
            '<div class="' + FG + '"><label class="' + LBL + '">Last Name' + REQ + '</label><input class="' + INP + '" id="ue_last" value="' + esc(u.last_name || '') + '"></div>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Email Address' + REQ + '</label><input class="' + INP + '" id="ue_email" type="email" value="' + esc(u.email || '') + '"></div>';
    if (role !== 'patient') fields += dobField('ue_dob', u.date_of_birth ? String(u.date_of_birth).slice(0, 10) : '', STAFF_MIN_AGE, false);

    if (isDentist) {
      const DEGREE_PRESETS = ['Doctor of Dental Medicine', 'Doctor of Dental Surgery', 'Bachelor of Dental Surgery', 'Master of Science in Dentistry'];
      const degreeOpts = [...new Set([...DEGREE_PRESETS, ...cache.map((x) => (x.degree || '').trim()).filter(Boolean)])];
      fields +=
        '<div class="' + FG + '"><label class="' + LBL + '">Degree' + REQ + '</label><input class="' + INP + '" id="ue_degree" list="ue_degreeList" autocomplete="off" value="' + esc(u.degree || '') + '">' +
          '<datalist id="ue_degreeList">' + degreeOpts.map((d) => '<option value="' + esc(d) + '"></option>').join('') + '</datalist></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">PRC License No.' + REQ + '</label><input class="' + INP + '" id="ue_prc" inputmode="numeric" maxlength="7" value="' + esc(u.prc_license || '') + '">' +
          '<div class="field-err" id="ue_prcErr" hidden></div></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Doctor Type</label><select class="' + INP + '" id="ue_doctype">' +
          '<option value="owner"' + (u.doctor_type === 'owner' ? ' selected' : '') + '>' + DOCTOR_TYPES.owner + '</option>' +
          '<option value="roaming"' + (u.doctor_type !== 'owner' ? ' selected' : '') + '>' + DOCTOR_TYPES.roaming + '</option>' +
        '</select></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Years of Experience</label><input class="' + INP + '" id="ue_years" type="number" min="0" value="' + esc(u.years_experience ?? '') + '"></div>' +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Specialties' + REQ + ' <span class="text-steel-400 dark:text-steel-500">(select at least one)</span></label><div class="grid grid-cols-2 gap-2" id="ue_specList">' +
          Object.entries(SPECS).map(([k, l]) => '<label class="flex items-center gap-2 font-medium"><input type="checkbox" class="ue_spec accent-accent" value="' + k + '"' + (selectedSpecs.has(k) ? ' checked' : '') + '> ' + esc(l) + '</label>').join('') +
        '</div></div>' +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">About the Doctor</label><textarea class="' + INP + ' min-h-[72px] resize-y" id="ue_about">' + esc(u.about || '') + '</textarea></div>';
    } else if (role === 'patient') {
      fields += dobField('ue_dob', u.date_of_birth ? String(u.date_of_birth).slice(0, 10) : '') +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Phone Number' + REQ + '</label><input class="' + INP + '" type="tel" inputmode="numeric" id="ue_phone" value="' + esc(u.contact_number || '') + '"></div>';
    } else if (role === 'receptionist' || role === 'cashier' || role === 'dental_assistant') {
      fields +=
        '<div class="' + FG + '"><label class="' + LBL + '">Phone Number' + REQ + '</label><input class="' + INP + '" type="tel" inputmode="numeric" id="ue_phone" value="' + esc(u.contact_number || '') + '"></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Years of Experience</label><input class="' + INP + '" id="ue_years" type="number" min="0" value="' + esc(u.years_experience ?? '') + '"></div>' +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">About</label><textarea class="' + INP + ' min-h-[72px] resize-y" id="ue_about">' + esc(u.about || '') + '</textarea></div>';
    }
    // admin: name + email only, nothing else appended.

    openModal(modalShell('Edit ' + ROLES[fromRole].label + ': ' + (u.full_name || ''),
      '<div class="grid grid-cols-2 gap-3.5">' + fields + '</div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="ue_save">' + (moving ? 'Change Role & Save' : 'Save Changes') + '</button>'));
    if (g('ue_roleType')) g('ue_roleType').addEventListener('change', (e) => {
      // Re-draw for the picked role, keeping what was typed.
      const keepU = Object.assign({}, u, {
        first_name: val('ue_first'), last_name: val('ue_last'), email: val('ue_email'),
        contact_number: g('ue_phone') ? val('ue_phone') : u.contact_number,
        years_experience: g('ue_years') ? val('ue_years') : u.years_experience,
        about: g('ue_about') ? val('ue_about') : u.about,
      });
      editUserModal(keepU, e.target.value, fromRole);
    });

    if (g('ue_phone')) wirePhoneField(g('ue_phone'), { submitBtn: g('ue_save') });
    wireDob('ue_dob');

    const prcInput = g('ue_prc');
    if (prcInput) {
      const prcErr = g('ue_prcErr');
      const validatePrc = () => {
        prcInput.value = prcInput.value.replace(/\D+/g, '').slice(0, 7);
        const bad = prcInput.value !== '' && prcInput.value.length !== 7;
        prcInput.classList.toggle('is-invalid', bad);
        if (prcErr) { prcErr.hidden = !bad; if (bad) prcErr.textContent = 'PRC License No. must be exactly 7 digits.'; }
        if (g('ue_save')) g('ue_save').disabled = bad;
      };
      prcInput.addEventListener('input', validatePrc);
      prcInput.addEventListener('blur', validatePrc);
    }

    g('ue_save').addEventListener('click', async () => {
      if (!val('ue_first') || !val('ue_last')) return toast('Enter the first and last name.', 'err');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('ue_email'))) return toast('Enter a valid email address.', 'err');
      if (g('ue_phone') && !g('ue_phone')._phoneOk(false)) return toast('Enter a valid phone number. It is required. ' + PH_MOBILE_MSG, 'err');

      const body = {
        user_id: u.user_id, role,
        first_name: val('ue_first'), last_name: val('ue_last'),
        email: val('ue_email'),
      };
      if (isDentist) {
        body.doctor_type = val('ue_doctype') || 'roaming';
        body.specialties = [...document.querySelectorAll('.ue_spec:checked')].map((c) => c.value);
        body.degree = val('ue_degree');
        body.prc_license = val('ue_prc');
        body.years_experience = val('ue_years');
        body.about = val('ue_about');
        if (!body.degree) return toast('Enter the doctor’s degree.', 'err');
        if (!/^\d{7}$/.test(body.prc_license)) return toast('Enter a valid 7-digit PRC License No.', 'err');
        if (!body.specialties.length) return toast('Select at least one specialty.', 'err');
      }
      if (role === 'receptionist' || role === 'cashier' || role === 'dental_assistant') {
        body.contact_number = val('ue_phone');
        body.years_experience = val('ue_years');
        body.about = val('ue_about');
      }
      if (role === 'patient') {
        body.contact_number = val('ue_phone');
        body.date_of_birth = val('ue_dob');
        if (!dobOk(body.date_of_birth)) return toast('Enter the patient’s date of birth.', 'err');
      } else {
        body.date_of_birth = val('ue_dob');
        if (!dobOk(body.date_of_birth)) return toast(DOB_STAFF_MSG, 'err');
      }

      try {
        await api('users.php?action=update', { method: 'POST', body });
        closeModal();
        if (moving) { toast((u.full_name || 'The account') + ' is now ' + (role === 'admin' ? 'an ' : 'a ') + ROLES[role].label + '.'); showList(role); }
        else { toast('Profile updated.'); load(); }
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  function editAssistantModal(u) {
    openModal(modalShell('Edit Assistant Profile',
      '<div class="grid grid-cols-2 gap-3.5">' +
        (u.login_user_id ? roleField('u_rroleType', 'dental_assistant', 'Pick another role to move this same account (no new account is made).') : '') +
        '<div class="' + FG + '"><label class="' + LBL + '">First Name' + REQ + '</label><input class="' + INP + '" id="u_rfirst" value="' + esc(u.first_name || '') + '"></div>' +
                '<div class="' + FG + '"><label class="' + LBL + '">Last Name' + REQ + '</label><input class="' + INP + '" id="u_rlast" value="' + esc(u.last_name || '') + '"></div>' +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Email Address' + REQ + '</label><input class="' + INP + '" id="u_remail" type="email" value="' + esc(u.email || '') + '"></div>' +
        dobField('u_rdob', u.date_of_birth ? String(u.date_of_birth).slice(0, 10) : '', STAFF_MIN_AGE, false) +
        (u.login_user_id ? '' : '<div class="' + FG + ' col-span-2"><span class="' + HINT + '">This assistant has no login yet. Saving creates one and shows a temporary password once.</span></div>') +
        '<div class="' + FG + '"><label class="' + LBL + '">Phone Number' + REQ + '</label><input class="' + INP + '" id="u_rphone" type="tel" inputmode="numeric" value="' + esc(u.contact_number || '') + '"></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Years of Experience</label><input class="' + INP + '" id="u_ryears" type="number" min="0" value="' + esc(u.years_experience ?? '') + '"></div>' +
        '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">About</label><textarea class="' + INP + ' min-h-[72px] resize-y" id="u_rabout">' + esc(u.about || '') + '</textarea></div>' +
      '</div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="u_rnsave">Save</button>'));
    if (g('u_rphone')) wirePhoneField(g('u_rphone'), { submitBtn: g('u_rnsave') });
    wireDob('u_rdob');
    if (g('u_rroleType')) g('u_rroleType').addEventListener('change', (e) => {
      editUserModal(Object.assign({}, u, {
        user_id: u.login_user_id, _roster: u,
        first_name: val('u_rfirst'), last_name: val('u_rlast'), email: val('u_remail'),
        contact_number: val('u_rphone'), years_experience: val('u_ryears'), about: val('u_rabout'),
      }), e.target.value, 'dental_assistant');
    });
    g('u_rnsave').addEventListener('click', async () => {
      if (!val('u_rfirst') || !val('u_rlast')) return toast('Enter the first and last name.', 'err');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('u_remail'))) return toast('Enter a valid email address. It is needed for password reset.', 'err');
      if (g('u_rphone') && !g('u_rphone')._phoneOk(false)) return toast('Enter a valid phone number. It is required. ' + PH_MOBILE_MSG, 'err');
      if (!dobOk(val('u_rdob'))) return toast(DOB_STAFF_MSG, 'err');
      try {
        const res = await api('assistants.php?action=update', { method: 'POST',
          body: {
            assistant_id: u.user_id,
            first_name: val('u_rfirst'), last_name: val('u_rlast'),
            contact_number: val('u_rphone'), email: val('u_remail'),
            years_experience: val('u_ryears'), about: val('u_rabout'),
            date_of_birth: val('u_rdob'),
          } });
        if (res.temp_password) showCreds(res); else closeModal();
        toast('Profile updated.'); load();
      } catch (err) { toast(err.message, 'err'); }
    });
  }

  /* ---------- archive ---------- */
  function archiveModal() {
    const archived = cache.filter((u) => u.is_active == 0);
    openModal(modalShell('Archived ' + ROLES[role].plural,
      archived.length
        ? '<div class="overflow-x-auto"><table class="w-full border-collapse text-[13px] [&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 [&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:dark:border-white/10"><thead><tr><th>Avatar</th><th>Name</th><th>Email</th><th>' +
          (role === 'dental_assistant' ? 'Date Added' : 'Registered') + '</th><th></th></tr></thead><tbody>' +
          archived.map((u) =>
            '<tr>' + avatarCell(u) + '<td><strong>' + esc(u.full_name) + '</strong></td><td>' + esc(u.email || 'N/A') + '</td>' +
            '<td class="text-steel-400 dark:text-steel-500">' + (u.created_at ? fmtDate(u.created_at.slice(0, 10)) : 'N/A') + '</td>' +
            '<td><button class="' + BTN_SM + PRIMARY + '" data-restore="' + u.user_id + '">Restore</button></td></tr>'
          ).join('') + '</tbody></table></div>'
        : '<p class="py-7 text-center text-steel-400 dark:text-steel-500">No archived ' + ROLES[role].plural + '.</p>'), { wide: true });

    document.querySelector('#modalRoot .modal-body').addEventListener('click', async (e) => {
      const b = e.target.closest('[data-restore]');
      if (!b) return;
      const ep = role === 'dental_assistant'
        ? { path: 'assistants.php?action=toggle_active', body: { assistant_id: +b.dataset.restore } }
        : { path: 'users.php?action=toggle_active', body: { user_id: +b.dataset.restore } };
      try {
        await api(ep.path, { method: 'POST', body: ep.body });
        closeModal(); toast(ROLES[role].label + ' restored.'); load();
      } catch (err) { toast(err.message, 'err'); }
    });
  }

  g('userRole').addEventListener('change', (e) => {
    role = e.target.value;
    PG.page = 1;
    load();
  });
  g('userSearch').addEventListener('input', () => { PG.page = 1; render(); });
  window.fitOnResize(() => { if (document.getElementById('userTable')) render(); });
  g('addUserBtn').addEventListener('click', () => openForm(role));
  g('archiveBtn').addEventListener('click', archiveModal);

  g('userTable').addEventListener('click', async (e) => {
    const arch = e.target.closest('[data-arch]');
    const reset = e.target.closest('[data-reset]');
    if (arch) {
      const ep = role === 'dental_assistant'
        ? { path: 'assistants.php?action=toggle_active', body: { assistant_id: +arch.dataset.arch } }
        : { path: 'users.php?action=toggle_active', body: { user_id: +arch.dataset.arch } };
      try { await api(ep.path, { method: 'POST', body: ep.body }); load(); }
      catch (err) { toast(err.message, 'err'); }
      return;
    }
    if (reset) {
      try {
        const { temp_password } = await api('users.php?action=reset_password', { method: 'POST', body: { user_id: +reset.dataset.reset } });
        openModal(modalShell('Temporary Password',
          '<p>The new temporary password is:</p><p class="text-xl font-bold text-navy-900 dark:text-steel-50">' + esc(temp_password) + '</p>' +
          '<p class="mt-2 text-[12px] text-steel-400 dark:text-steel-500">The account holder must set their own password the next time they sign in.</p>',
          '<button class="' + BTN + PRIMARY + '" data-close>Done</button>'));
      } catch (err) { toast(err.message, 'err'); }
      return;
    }
    // Clicking anywhere else on a row (the name, avatar, email, …) opens that
    // person's editable profile — the action buttons above already returned
    // before reaching here, so this only fires on the rest of the row.
    const row = e.target.closest('tr[data-edituser]');
    if (row) {
      const u = cache.find((x) => x.user_id == row.dataset.edituser);
      if (u) (role === 'dental_assistant' ? editAssistantModal(u) : editUserModal(u, role, role));
    }
  });

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
