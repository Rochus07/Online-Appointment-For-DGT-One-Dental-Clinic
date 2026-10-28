<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth();               // any authenticated user
$PAGE_TITLE = 'My Profile';
$ACTIVE = '';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

$meAvatar = avatarUrl($me['avatar'] ?? null);

// Patients: date of birth on file (health questions moved to the Patient
// Information Record, 2026-10-20).
$isPatient = $me['role'] === 'patient';
$health = ['date_of_birth' => '', 'emergency_contact_name' => '', 'emergency_contact_number' => '', 'allergies' => ''];
$missing = [];
if ($isPatient) {
    $hs = getDB()->prepare('SELECT date_of_birth, emergency_contact_name, emergency_contact_number, allergies FROM patient_records WHERE patient_id = ?');
    $hs->execute([(int) $me['user_id']]);
    $health = array_merge($health, array_map(fn ($v) => (string) ($v ?? ''), $hs->fetch() ?: []));
    $missing = patientProfileMissing((int) $me['user_id']);
}
// Date of birth for every role: patients in patient_records, staff on users
// (migration 072). Set once, then shown only as a locked Age.
$dobOnFile = $isPatient ? $health['date_of_birth'] : (string) ($me['date_of_birth'] ?? '');
// Required fields get a red star (2026-10-17). The phone is required for the
// roles whose accounts are created with one.
$req = '<span class="text-danger"> *</span>';
$phoneRequired = in_array($me['role'], ['patient', 'receptionist', 'cashier', 'dental_assistant'], true);
$initial  = strtoupper(substr($me['full_name'], 0, 1));

$cardCls    = 'rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';
$headCls    = 'mb-3.5 flex flex-wrap items-center justify-between gap-3';
$h2Cls      = 'text-[15px] text-navy-800 dark:text-steel-50';
$fgCls      = 'flex flex-col gap-1.5';
$lblCls     = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
$inputCls   = 'rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent disabled:bg-steel-50 dark:border-white/15 dark:bg-navy-900 dark:text-steel-50 dark:disabled:bg-white/5';
$hintCls    = 'text-[11px] text-steel-400 dark:text-steel-500';
$btnPrimary = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md bg-accent px-[15px] py-[9px] text-[13px] font-semibold text-white hover:bg-accent-dark';
$btnPrimSm  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md bg-accent px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-accent-dark';
$btnGhostSm = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';

ob_start();
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">My Profile</h1></div>
</div>

<?php if ($isPatient && $missing): ?>
<div id="profileTodo" class="mb-4 rounded-[10px] border border-warn/40 bg-warn-soft px-4 py-3.5 text-[13.5px] text-[#8a5300] dark:border-warn/40 dark:bg-warn/15 dark:text-[#f3c77e]">
    <strong class="block text-[14px]">Please complete your profile</strong>
    <span>Your dentist uses these details to treat you safely. Still missing:</span>
    <ul class="mt-1.5 flex flex-wrap gap-1.5">
        <?php foreach ($missing as $m): ?>
            <li class="rounded-full bg-white/70 px-2.5 py-0.5 text-[12.5px] font-semibold dark:bg-white/10"><?= e($m) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="<?= $cardCls ?>">
    <div class="<?= $headCls ?>"><h2 class="<?= $h2Cls ?>">Profile Photo</h2></div>
    <div class="flex flex-wrap items-center gap-5">
        <div class="grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-full bg-navy-700 text-[34px] font-bold text-white [&_img]:h-full [&_img]:w-full [&_img]:object-cover" id="photoPreview">
            <?php if ($meAvatar !== null): ?>
                <img src="<?= e($meAvatar) ?>" alt="Profile photo">
            <?php else: ?>
                <span><?= e($initial) ?></span>
            <?php endif; ?>
        </div>
        <div class="flex flex-col gap-1.5">
            <p class="<?= $hintCls ?>">JPG, PNG, WEBP or GIF. Maximum 2 MB.</p>
            <div class="flex flex-wrap gap-2">
                <button class="<?= $btnPrimSm ?>" id="photoPick" type="button">Upload New Photo</button>
                <button class="<?= $btnGhostSm ?>" id="photoRemove" type="button"
                        <?= $meAvatar === null ? 'hidden' : '' ?>>Remove</button>
            </div>
            <input type="file" id="photoInput" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
        </div>
    </div>
</div>

<div class="<?= $cardCls ?> mt-4">
    <div class="<?= $headCls ?>"><h2 class="<?= $h2Cls ?>">Personal Information</h2></div>
    <div class="grid gap-3.5 sm:grid-cols-2">
        <div class="<?= $fgCls ?>">
            <label class="<?= $lblCls ?>">First Name<?= $req ?></label>
            <input id="p_first" value="<?= e($me['first_name'] ?? '') ?>" class="<?= $inputCls ?>">
        </div>
        <div class="<?= $fgCls ?>">
            <label class="<?= $lblCls ?>">Last Name<?= $req ?></label>
            <input id="p_last" value="<?= e($me['last_name'] ?? '') ?>" class="<?= $inputCls ?>">
        </div>
        <div class="<?= $fgCls ?>">
            <label class="<?= $lblCls ?>">Username</label>
            <input value="<?= e($me['username']) ?>" disabled class="<?= $inputCls ?>">
        </div>
        <div class="<?= $fgCls ?>">
            <label class="<?= $lblCls ?>">Contact Number<?= $phoneRequired ? $req : '' ?></label>
            <input id="p_phone" type="tel" inputmode="numeric" value="<?= e($me['contact_number'] ?? '') ?>" class="<?= $inputCls ?>">
        </div>
        <div class="<?= $fgCls ?>">
            <label class="<?= $lblCls ?>">Role</label>
            <input value="<?= e(roleLabel($me['role'])) ?>" disabled class="<?= $inputCls ?>">
        </div>
        <!-- Shown as the Age once saved and can't be changed here (2026-10-17). -->
        <div class="<?= $fgCls ?>">
            <label class="<?= $lblCls ?>"><?= $dobOnFile !== '' ? 'Age' : 'Date of Birth' ?><?= $req ?></label>
            <div id="h_dobWrap" data-dob="<?= e(substr($dobOnFile, 0, 10)) ?>"></div>
            <?php if ($dobOnFile === ''): ?>
                <p class="<?= $hintCls ?>">Check it carefully. Once saved, it can't be changed here.</p>
            <?php endif; ?>
        </div>
        <div class="<?= $fgCls ?> sm:col-span-2">
            <label class="<?= $lblCls ?>">Email<?= $req ?></label>
            <input id="p_email" type="email" value="<?= e($me['email'] ?? '') ?>" class="<?= $inputCls ?>">
        </div>
        <div class="<?= $fgCls ?> sm:col-span-2">
            <button class="<?= $btnPrimary ?>" id="saveInfo">Save Changes</button>
        </div>
    </div>
</div>

<div class="<?= $cardCls ?> mt-4">
    <div class="<?= $headCls ?>"><h2 class="<?= $h2Cls ?>">Change Password</h2></div>
    <?php if (!empty($me['must_change_password'])): ?>
        <div id="tempPwNote" class="mb-3.5 rounded-md border-l-4 border-warn bg-warn-soft px-3.5 py-2.5 text-[13px] text-warn dark:bg-warn/20">
            You're signed in with a temporary password. Set your own password below to continue to your dashboard.
        </div>
    <?php endif; ?>
    <div class="grid gap-3.5 sm:grid-cols-2">
        <div class="<?= $fgCls ?> sm:col-span-2">
            <label class="<?= $lblCls ?>">Current Password</label>
            <div class="pw-field relative">
                <input id="pw_current" type="password" autocomplete="current-password" class="<?= $inputCls ?> w-full pr-10">
                <?= pwToggleBtn() ?>
            </div>
        </div>
        <div class="<?= $fgCls ?>">
            <label class="<?= $lblCls ?>">New Password</label>
            <div class="pw-field relative">
                <input id="pw_new" type="password" autocomplete="new-password" class="<?= $inputCls ?> w-full pr-10">
                <?= pwToggleBtn() ?>
            </div>
        </div>
        <div class="<?= $fgCls ?>">
            <label class="<?= $lblCls ?>">Confirm New Password</label>
            <div class="pw-field relative">
                <input id="pw_confirm" type="password" autocomplete="new-password" class="<?= $inputCls ?> w-full pr-10">
                <?= pwToggleBtn() ?>
            </div>
        </div>
        <div class="<?= $fgCls ?> sm:col-span-2">
            <button class="<?= $btnPrimary ?>" id="savePw">Update Password</button>
        </div>
    </div>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const val = (id) => document.getElementById(id).value.trim();

  wirePhoneField(document.getElementById('p_phone'), { submitBtn: document.getElementById('saveInfo') });

  // Date of birth (patients): a saved one shows as a locked Age, no Change link.
  const dobWrap = document.getElementById('h_dobWrap');
  const dobOnFile = !!(dobWrap && dobWrap.dataset.dob);
  if (dobWrap) {
    dobWrap.innerHTML = dobSelectsHtml('h_dob', dobWrap.dataset.dob || '', '__INPUT_CLS__', { swap: true, minAge: __MIN_AGE__, maxAge: __MAX_AGE__ });
    wireDobSelects('h_dob');
  }

  document.getElementById('saveInfo').addEventListener('click', async () => {
    if (!document.getElementById('p_phone')._phoneOk(!__PHONE_REQ__)) return toast((__PHONE_REQ__ ? 'Enter your contact number. ' : '') + PH_MOBILE_MSG, 'err');
    try {
      if (!val('p_first') || !val('p_last')) return toast('Enter your first and last name.', 'err');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('p_email'))) return toast('Enter a valid email address. It is needed for password reset.', 'err');
      if (dobWrap && !dobOnFile && !val('h_dob')) return toast('Enter your date of birth.', 'err');
      const body = {
        first_name: val('p_first'),
        last_name: val('p_last'),
        contact_number: val('p_phone'),
        email: val('p_email'),
      };
      if (dobWrap && !dobOnFile && val('h_dob')) body.date_of_birth = val('h_dob');
      await api('profile.php?action=update', { method: 'POST', body });
      toast('Profile updated.');
      setTimeout(() => location.reload(), 600);
    } catch (e) { toast(e.message, 'err'); }
  });

  // A missing phone glows so the patient sees what to fill in.
  if (document.getElementById('profileTodo')) {
    const mark = () => document.getElementById('p_phone').classList.toggle('!border-warn', !val('p_phone'));
    mark();
    document.getElementById('p_phone').addEventListener('input', mark);
  }

  window.attachPwStrength(document.getElementById('pw_new'));
  window.wirePwMatch(document.getElementById('pw_new'), document.getElementById('pw_confirm'));

  document.getElementById('savePw').addEventListener('click', async () => {
    const cur = val('pw_current'), nw = val('pw_new'), cf = val('pw_confirm');
    if (!window.isStrongPassword(nw)) return toast(window.STRONG_PASSWORD_MSG, 'err');
    if (nw !== cf) return toast('The new passwords do not match.', 'err');
    try {
      await api('profile.php?action=password', { method: 'POST', body: {
        current_password: cur, new_password: nw,
      }});
      toast('Password changed.');
      ['pw_current', 'pw_new', 'pw_confirm'].forEach((id) => (document.getElementById(id).value = ''));
      const tempNote = document.getElementById('tempPwNote');
      if (tempNote) {
        // It was a forced temporary-password change: the gate is now cleared,
        // so continue to the account's normal home page.
        tempNote.remove();
        setTimeout(() => { location.href = '__HOME_URL__'; }, 700);
      }
    } catch (e) { toast(e.message, 'err'); }
  });

  const input = document.getElementById('photoInput');
  document.getElementById('photoPick').addEventListener('click', () => input.click());

  // Shrinks any browser-readable photo to at most 1000px on its long side and
  // re-encodes it as JPEG, so large phone/camera photos stay under the 2 MB limit.
  function shrinkPhoto(file) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        const MAX = 1000;
        const scale = Math.min(1, MAX / Math.max(img.naturalWidth, img.naturalHeight));
        const w = Math.max(1, Math.round(img.naturalWidth * scale));
        const h = Math.max(1, Math.round(img.naturalHeight * scale));
        const canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, w, h);
        ctx.drawImage(img, 0, 0, w, h);
        URL.revokeObjectURL(url);
        canvas.toBlob(b => b ? resolve(b) : reject(new Error('Could not process the image.')), 'image/jpeg', 0.88);
      };
      img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('This image format is not supported. Use a JPG, PNG, WEBP or GIF photo.')); };
      img.src = url;
    });
  }

  input.addEventListener('change', async () => {
    const file = input.files[0];
    if (!file) return;
    if (file.size > 25 * 1024 * 1024) { toast('That file is too large. Choose a photo under 25 MB.', 'err'); input.value = ''; return; }
    const fd = new FormData();
    try {
      // Animated GIFs that already fit are sent as-is so the animation survives.
      if (file.type === 'image/gif' && file.size <= 2 * 1024 * 1024) fd.append('photo', file);
      else fd.append('photo', await shrinkPhoto(file), 'photo.jpg');
    } catch (e) { toast(e.message, 'err'); input.value = ''; return; }
    try {
      const res = await fetch('/dgt_dental_clinic/api/profile.php?action=avatar', {
        method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd,
      });
      const data = await res.json();
      if (!res.ok || data.ok === false) throw new Error(data.error || 'Upload failed.');
      toast('Photo updated.');
      setTimeout(() => location.reload(), 600);
    } catch (e) { toast(e.message, 'err'); }
    input.value = '';
  });

  document.getElementById('photoRemove').addEventListener('click', async () => {
    try {
      await api('profile.php?action=avatar_remove', { method: 'POST', body: {} });
      toast('Photo removed.');
      setTimeout(() => location.reload(), 600);
    } catch (e) { toast(e.message, 'err'); }
  });
})();
JS;
$PAGE_SCRIPT = str_replace('__INPUT_CLS__', $inputCls, $PAGE_SCRIPT);
$PAGE_SCRIPT = str_replace('__PHONE_REQ__', $phoneRequired ? 'true' : 'false', $PAGE_SCRIPT);
$PAGE_SCRIPT = str_replace('__MIN_AGE__', (string) ($isPatient ? 0 : STAFF_MIN_AGE), $PAGE_SCRIPT);
$PAGE_SCRIPT = str_replace('__MAX_AGE__', (string) ($isPatient ? 120 : ACCOUNT_MAX_AGE), $PAGE_SCRIPT);
$PAGE_SCRIPT = str_replace('__HOME_URL__', $me['role'] === 'patient' ? '/dgt_dental_clinic/home' : roleHome($me['role']), $PAGE_SCRIPT);
require __DIR__ . '/../../includes/layout.php';
