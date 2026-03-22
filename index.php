<?php
/**
 * Employee ID Card Generator
 * Single-file PHP + HTML + JS app
 */

declare(strict_types=1);

$errors = [];
$successMessage = '';
$uploads = [
    'company_logo' => '',
    'employee_photo' => '',
];

function esc(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function save_image_upload(string $field, string $targetDir, array &$errors): string
{
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = "Upload failed for {$field}.";
        return '';
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $mime = (string)(mime_content_type($tmp) ?: '');
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($allowed[$mime])) {
        $errors[] = "{$field} must be JPG, PNG, or WEBP.";
        return '';
    }

    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $errors[] = "{$field} exceeds the 5MB limit.";
        return '';
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        $errors[] = 'Could not create uploads folder.';
        return '';
    }

    $safeName = $field . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dest = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($tmp, $dest)) {
        $errors[] = "Could not store {$field}.";
        return '';
    }

    return 'uploads/' . $safeName;
}

$data = [
    'employee_name' => 'Employee Name',
    'employee_id' => 'EMP-0001',
    'designation' => 'Designation',
    'department' => 'Department',
    'phone' => '+1 000 000 0000',
    'email' => 'employee@company.com',
    'blood_group' => 'O+',
    'address' => 'Address, City',
    'joining_date' => date('Y-m-d'),
    'expiry_date' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $key => $default) {
        if (isset($_POST[$key])) {
            $data[$key] = esc((string)$_POST[$key]);
        }
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    $uploads['company_logo'] = save_image_upload('company_logo', $uploadDir, $errors);
    $uploads['employee_photo'] = save_image_upload('employee_photo', $uploadDir, $errors);

    if (!$errors) {
        $successMessage = 'Saved. Uploaded files were stored temporarily in /uploads.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Employee ID Card Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        .card-bg { background: linear-gradient(150deg, #ffffff 0%, #fff1f2 100%); }
        .card-top { background: linear-gradient(130deg, #dc2626 0%, #b91c1c 100%); }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen p-4 md:p-8">
<div class="max-w-7xl mx-auto">
    <header class="mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-red-700">Employee ID Card Generator</h1>
        <p class="text-sm text-slate-600">Live preview + export (PNG/JPG/PDF) with optional PHP submission.</p>
    </header>

    <?php if ($errors): ?>
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-red-700">
            <ul class="list-disc pl-5 space-y-1">
                <?php foreach ($errors as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-emerald-700"><?= esc($successMessage) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 md:p-6">
            <h2 class="text-lg font-semibold mb-4">Employee Details</h2>
            <form id="idForm" method="post" enctype="multipart/form-data" class="space-y-4" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="company_logo" class="block text-sm font-medium mb-1">Company Logo (optional)</label>
                        <input id="company_logo" name="company_logo" type="file" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg border p-2 text-sm focus:ring-2 focus:ring-red-200" />
                        <p class="text-xs text-slate-500 mt-1">If empty, default prefixed logo is shown.</p>
                        <p id="company_logo_error" class="text-xs text-red-600 mt-1 hidden"></p>
                    </div>
                    <div>
                        <label for="employee_photo" class="block text-sm font-medium mb-1">Employee Photo</label>
                        <input id="employee_photo" name="employee_photo" type="file" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg border p-2 text-sm focus:ring-2 focus:ring-red-200" />
                        <p class="text-xs text-slate-500 mt-1">JPG/PNG/WEBP up to 5MB.</p>
                        <p id="employee_photo_error" class="text-xs text-red-600 mt-1 hidden"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="employee_name" class="block text-sm font-medium mb-1">Employee Name</label>
                        <input id="employee_name" name="employee_name" type="text" value="<?= $data['employee_name'] ?>" class="w-full rounded-lg border p-2.5 focus:ring-2 focus:ring-red-200" />
                    </div>
                    <div>
                        <label for="employee_id" class="block text-sm font-medium mb-1">Employee ID</label>
                        <input id="employee_id" name="employee_id" type="text" value="<?= $data['employee_id'] ?>" class="w-full rounded-lg border p-2.5 focus:ring-2 focus:ring-red-200" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="designation" class="block text-sm font-medium mb-1">Designation</label>
                        <input id="designation" name="designation" type="text" value="<?= $data['designation'] ?>" class="w-full rounded-lg border p-2.5 focus:ring-2 focus:ring-red-200" />
                    </div>
                    <div>
                        <label for="department" class="block text-sm font-medium mb-1">Department</label>
                        <input id="department" name="department" type="text" value="<?= $data['department'] ?>" class="w-full rounded-lg border p-2.5 focus:ring-2 focus:ring-red-200" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium mb-1">Phone Number</label>
                        <input id="phone" name="phone" type="tel" value="<?= $data['phone'] ?>" class="w-full rounded-lg border p-2.5 focus:ring-2 focus:ring-red-200" />
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium mb-1">Email</label>
                        <input id="email" name="email" type="email" value="<?= $data['email'] ?>" class="w-full rounded-lg border p-2.5 focus:ring-2 focus:ring-red-200" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="blood_group" class="block text-sm font-medium mb-1">Blood Group</label>
                        <input id="blood_group" name="blood_group" type="text" value="<?= $data['blood_group'] ?>" class="w-full rounded-lg border p-2.5 focus:ring-2 focus:ring-red-200" />
                    </div>
                    <div>
                        <label for="address" class="block text-sm font-medium mb-1">Address</label>
                        <input id="address" name="address" type="text" value="<?= $data['address'] ?>" class="w-full rounded-lg border p-2.5 focus:ring-2 focus:ring-red-200" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="joining_date" class="block text-sm font-medium mb-1">Date of Joining</label>
                        <input id="joining_date" name="joining_date" type="date" value="<?= $data['joining_date'] ?>" class="w-full rounded-lg border p-2.5 focus:ring-2 focus:ring-red-200" />
                    </div>
                    <div>
                        <label for="expiry_date" class="block text-sm font-medium mb-1">Expiry Date (optional)</label>
                        <input id="expiry_date" name="expiry_date" type="date" value="<?= $data['expiry_date'] ?>" class="w-full rounded-lg border p-2.5 focus:ring-2 focus:ring-red-200" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 pt-1">
                    <button type="submit" class="rounded-lg bg-red-600 text-white px-4 py-2.5 hover:bg-red-700 focus:ring-2 focus:ring-red-300">Save (PHP Submit)</button>
                    <button type="button" id="loadDemo" class="rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-2.5 hover:bg-red-100 focus:ring-2 focus:ring-red-200">Load Demo</button>
                    <button type="button" id="downloadPng" class="rounded-lg bg-slate-700 text-white px-4 py-2.5 hover:bg-slate-800 focus:ring-2 focus:ring-slate-300">Download PNG</button>
                    <button type="button" id="downloadJpg" class="rounded-lg bg-slate-700 text-white px-4 py-2.5 hover:bg-slate-800 focus:ring-2 focus:ring-slate-300">Download JPG</button>
                    <button type="button" id="downloadPdf" class="rounded-lg bg-slate-700 text-white px-4 py-2.5 hover:bg-slate-800 focus:ring-2 focus:ring-slate-300">Download PDF</button>
                </div>
            </form>
        </section>

        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 md:p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">Live Preview</h2>
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input id="showBackSide" type="checkbox" class="rounded border-slate-300 text-red-600 focus:ring-red-200" />
                    Show Back Side
                </label>
            </div>

            <div class="flex justify-center">
                <article id="idCard" class="card-bg w-[300px] h-[500px] rounded-2xl overflow-hidden border border-red-100 shadow-lg flex flex-col" aria-label="Employee ID card front preview">
                    <div class="card-top text-white p-4">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div id="logoFallback" class="h-12 w-12 rounded-full bg-white text-red-700 font-bold text-xs flex items-center justify-center">LOGO</div>
                                <img id="logoPreview" src="<?= $uploads['company_logo'] ? esc($uploads['company_logo']) : '' ?>" alt="Company logo" class="h-12 w-12 rounded-full object-cover hidden" />
                                <div>
                                    <p class="font-semibold">Your Company</p>
                                    <p class="text-xs opacity-90">Employee Identity Card</p>
                                </div>
                            </div>
                            <span class="text-[10px] bg-white/20 px-2 py-1 rounded-full">VALID</span>
                        </div>
                    </div>

                    <div class="p-4 flex-1 flex flex-col gap-4">
                        <div class="text-center">
                            <img id="photoPreview" src="<?= $uploads['employee_photo'] ? esc($uploads['employee_photo']) : '' ?>" alt="Employee" class="h-24 w-24 rounded-xl object-cover border-2 border-red-300 mx-auto <?= $uploads['employee_photo'] ? '' : 'hidden' ?>" />
                            <div id="photoFallback" class="h-24 w-24 rounded-xl border-2 border-dashed border-red-300 text-red-500 text-xs font-medium flex items-center justify-center mx-auto <?= $uploads['employee_photo'] ? 'hidden' : '' ?>">Photo</div>
                            <h3 id="preview_name" class="text-xl font-bold text-red-700 mt-3"><?= $data['employee_name'] ?></h3>
                            <p id="preview_designation" class="text-sm font-medium"><?= $data['designation'] ?></p>
                            <p id="preview_department" class="text-xs text-slate-500"><?= $data['department'] ?></p>
                        </div>

                        <dl class="grid grid-cols-1 gap-1 text-sm">
                            <div><dt class="text-slate-500">Employee ID</dt><dd id="preview_employee_id" class="font-semibold"><?= $data['employee_id'] ?></dd></div>
                            <div><dt class="text-slate-500">Blood Group</dt><dd id="preview_blood_group" class="font-semibold"><?= $data['blood_group'] ?></dd></div>
                            <div><dt class="text-slate-500">Phone</dt><dd id="preview_phone" class="font-semibold"><?= $data['phone'] ?></dd></div>
                            <div><dt class="text-slate-500">Email</dt><dd id="preview_email" class="font-semibold break-all"><?= $data['email'] ?></dd></div>
                            <div><dt class="text-slate-500">Address</dt><dd id="preview_address" class="font-semibold"><?= $data['address'] ?></dd></div>
                        </dl>

                        <div class="text-xs border-t pt-2 mt-auto text-slate-700">
                            <p>Join: <span id="preview_joining_date" class="font-semibold"><?= $data['joining_date'] ?></span></p>
                            <p>Expiry: <span id="preview_expiry_date" class="font-semibold"><?= $data['expiry_date'] ?: 'N/A' ?></span></p>
                        </div>
                    </div>
                </article>
            </div>

            <div id="backSideWrap" class="hidden mt-4 flex justify-center">
                <article class="card-bg w-[300px] h-[500px] rounded-2xl overflow-hidden border border-red-100 shadow-lg">
                    <div class="card-top text-white p-4">
                        <h3 class="font-semibold">Card Back Side</h3>
                        <p class="text-xs opacity-90">Property of Your Company</p>
                    </div>
                    <div class="p-4 space-y-3 text-sm">
                        <p>If found please return to HR Department.</p>
                        <div class="rounded-xl border bg-white p-3">
                            <p class="text-xs text-slate-500">Reference</p>
                            <p class="font-semibold">Name: <span id="back_preview_name">Employee Name</span></p>
                            <p class="font-semibold">ID: <span id="back_preview_id">EMP-0001</span></p>
                        </div>
                        <p class="text-xs text-slate-500">This card is non-transferable and should be carried at work.</p>
                    </div>
                </article>
            </div>
        </section>
    </div>

    <section class="mt-6 bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <h3 class="font-semibold">Local Run Instructions</h3>
        <ol class="list-decimal pl-5 mt-2 text-sm space-y-1">
            <li>Open terminal in this folder.</li>
            <li>Run: <code class="bg-slate-100 px-1 rounded">php -S localhost:8000</code></li>
            <li>Open: <code class="bg-slate-100 px-1 rounded">http://localhost:8000/index.php</code></li>
        </ol>
    </section>
</div>

<script>
const placeholders = {
    employee_name: 'Employee Name',
    employee_id: 'EMP-0001',
    designation: 'Designation',
    department: 'Department',
    phone: '+1 000 000 0000',
    email: 'employee@company.com',
    blood_group: 'O+',
    address: 'Address, City',
    joining_date: 'YYYY-MM-DD',
    expiry_date: 'N/A'
};

const fieldMap = [
    ['employee_name', 'preview_name'],
    ['employee_name', 'back_preview_name'],
    ['employee_id', 'preview_employee_id'],
    ['employee_id', 'back_preview_id'],
    ['designation', 'preview_designation'],
    ['department', 'preview_department'],
    ['phone', 'preview_phone'],
    ['email', 'preview_email'],
    ['blood_group', 'preview_blood_group'],
    ['address', 'preview_address'],
    ['joining_date', 'preview_joining_date'],
    ['expiry_date', 'preview_expiry_date']
];

const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

function updateField(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (!input || !preview) return;
    const val = input.value.trim();
    preview.textContent = val || placeholders[inputId] || '-';
}

function setFileError(fieldId, msg = '') {
    const el = document.getElementById(`${fieldId}_error`);
    if (!el) return;
    if (!msg) {
        el.textContent = '';
        el.classList.add('hidden');
        return;
    }
    el.textContent = msg;
    el.classList.remove('hidden');
}

function validateImage(file) {
    if (!ALLOWED_TYPES.includes(file.type)) return 'Please upload JPG, PNG, or WEBP image.';
    if (file.size > MAX_IMAGE_SIZE) return 'Image must be 5MB or less.';
    return '';
}

function bindImagePreview(inputId, imageId, fallbackId) {
    const input = document.getElementById(inputId);
    const image = document.getElementById(imageId);
    const fallback = document.getElementById(fallbackId);
    if (!input || !image || !fallback) return;

    input.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (!file) {
            image.src = '';
            image.classList.add('hidden');
            fallback.classList.remove('hidden');
            setFileError(inputId);
            return;
        }

        const err = validateImage(file);
        if (err) {
            input.value = '';
            image.src = '';
            image.classList.add('hidden');
            fallback.classList.remove('hidden');
            setFileError(inputId, err);
            return;
        }

        setFileError(inputId);
        const reader = new FileReader();
        reader.onload = (evt) => {
            image.src = evt.target?.result || '';
            image.classList.remove('hidden');
            fallback.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    });
}

async function captureCard() {
    const card = document.getElementById('idCard');
    if (typeof html2canvas !== 'function') throw new Error('html2canvas not available');
    return html2canvas(card, { scale: 3, useCORS: true, backgroundColor: '#ffffff' });
}

async function downloadImage(ext) {
    try {
        const canvas = await captureCard();
        const mime = ext === 'jpg' ? 'image/jpeg' : 'image/png';
        const href = canvas.toDataURL(mime, 1.0);
        const a = document.createElement('a');
        a.href = href;
        a.download = `employee-id-card.${ext}`;
        a.click();
    } catch (e) {
        alert('Image export failed. Please refresh and try again.');
        console.error(e);
    }
}

async function downloadPdf() {
    try {
        const canvas = await captureCard();
        if (!window.jspdf?.jsPDF) throw new Error('jsPDF not available');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: 'portrait', unit: 'px', format: [canvas.width, canvas.height] });
        const img = canvas.toDataURL('image/png', 1.0);
        pdf.addImage(img, 'PNG', 0, 0, canvas.width, canvas.height);
        pdf.save('employee-id-card.pdf');
    } catch (e) {
        alert('PDF export failed. Please refresh and try again.');
        console.error(e);
    }
}

fieldMap.forEach(([inputId, previewId]) => {
    const input = document.getElementById(inputId);
    if (!input) return;
    updateField(inputId, previewId);
    input.addEventListener('input', () => updateField(inputId, previewId));
    input.addEventListener('change', () => updateField(inputId, previewId));
});

bindImagePreview('company_logo', 'logoPreview', 'logoFallback');
bindImagePreview('employee_photo', 'photoPreview', 'photoFallback');

document.getElementById('showBackSide').addEventListener('change', (e) => {
    document.getElementById('backSideWrap').classList.toggle('hidden', !e.target.checked);
});

document.getElementById('loadDemo').addEventListener('click', () => {
    const demo = {
        employee_name: 'Ava Thompson',
        employee_id: 'EMP-4721',
        designation: 'Software Engineer',
        department: 'Engineering',
        phone: '+1 415 555 0184',
        email: 'ava.thompson@yourcompany.com',
        blood_group: 'A+',
        address: '124 Howard St, San Francisco, CA',
        joining_date: '2025-01-10',
        expiry_date: '2028-01-10'
    };

    for (const [key, value] of Object.entries(demo)) {
        const input = document.getElementById(key);
        if (input) {
            input.value = value;
            fieldMap.filter(([id]) => id === key).forEach(([id, previewId]) => updateField(id, previewId));
        }
    }
});

document.getElementById('downloadPng').addEventListener('click', () => downloadImage('png'));
document.getElementById('downloadJpg').addEventListener('click', () => downloadImage('jpg'));
document.getElementById('downloadPdf').addEventListener('click', downloadPdf);
</script>
</body>
</html>
