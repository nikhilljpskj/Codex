<?php
/**
 * Employee ID Card Generator
 * - Minimal PHP backend for optional form handling and safe temporary uploads.
 * - Frontend provides live preview and export options.
 */

declare(strict_types=1);

$errors = [];
$successMessage = '';
$storedUploads = [
    'company_logo' => '',
    'employee_photo' => '',
];

/**
 * Basic output-safe sanitization.
 */
function clean_input(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Save upload safely to a local temporary directory.
 */
function handle_image_upload(string $fieldName, string $uploadDir, array &$errors): string
{
    if (empty($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    $file = $_FILES[$fieldName];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = "Upload failed for {$fieldName}.";
        return '';
    }

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $mimeType = (string) (mime_content_type($tmpPath) ?: '');

    if (!in_array($mimeType, $allowedMime, true)) {
        $errors[] = "{$fieldName} must be JPG, PNG, or WEBP.";
        return '';
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if (((int) ($file['size'] ?? 0)) > $maxSize) {
        $errors[] = "{$fieldName} exceeds 5MB size limit.";
        return '';
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        $errors[] = 'Could not create upload directory.';
        return '';
    }

    $ext = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'bin',
    };

    $safeName = $fieldName . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destination = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($tmpPath, $destination)) {
        $errors[] = "Could not store {$fieldName}.";
        return '';
    }

    return 'uploads/' . $safeName;
}

$submittedData = [
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
    foreach ($submittedData as $key => $defaultVal) {
        if (isset($_POST[$key])) {
            $submittedData[$key] = clean_input((string) $_POST[$key]);
        }
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    $storedUploads['company_logo'] = handle_image_upload('company_logo', $uploadDir, $errors);
    $storedUploads['employee_photo'] = handle_image_upload('employee_photo', $uploadDir, $errors);

    if (!$errors) {
        $successMessage = 'Form submitted successfully. Uploaded files (if any) were stored temporarily.';
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNa5Apcv4sYQGRM4Vv+5A/G4N2M4Rr8xk8x6UCJY2khb1xB9fieQshD+y/2f0DRN4x8A2x1f3sUE9jzupR1QnA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" integrity="sha512-qZvrH4xA7w4Yxq9fxf4Y0K5hvdM0V24W5HLrA2zLxT1Xf+g4u2lV6UN8fjl5YvC0oZgf9J4iKXQnH5xQhCzBgw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        .id-card-gradient {
            background: linear-gradient(145deg, #ffffff 0%, #fff5f5 100%);
        }

        .red-accent {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .field-note {
            font-size: 0.75rem;
            color: #6b7280;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-4 md:p-8 text-slate-800">
    <div class="max-w-7xl mx-auto">
        <header class="mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-red-700">Employee ID Card Generator</h1>
            <p class="text-sm text-slate-600 mt-1">Fill in details on the left and preview/export the ID card on the right.</p>
        </header>

        <?php if ($errors): ?>
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 p-3">
                <ul class="list-disc pl-5 space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?= clean_input($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 p-3">
                <?= clean_input($successMessage) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 md:p-6">
                <h2 class="text-lg font-semibold mb-4">Employee Details</h2>

                <form id="idForm" class="space-y-4" method="post" enctype="multipart/form-data" novalidate>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="company_logo" class="block text-sm font-medium mb-1">Company Logo (Optional)</label>
                            <input id="company_logo" name="company_logo" type="file" accept="image/png,image/jpeg,image/webp" class="w-full text-sm border rounded-lg p-2 focus:ring-2 focus:ring-red-200" />
                            <p class="field-note">If empty, a default prefixed logo is used.</p>
                            <p id="company_logo_error" class="text-xs text-red-600 mt-1 hidden"></p>
                        </div>
                        <div>
                            <label for="employee_photo" class="block text-sm font-medium mb-1">Employee Photo</label>
                            <input id="employee_photo" name="employee_photo" type="file" accept="image/png,image/jpeg,image/webp" class="w-full text-sm border rounded-lg p-2 focus:ring-2 focus:ring-red-200" />
                            <p class="field-note">Accepted: JPG/PNG/WEBP up to 5MB.</p>
                            <p id="employee_photo_error" class="text-xs text-red-600 mt-1 hidden"></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="employee_name" class="block text-sm font-medium mb-1">Employee Name</label>
                            <input id="employee_name" name="employee_name" type="text" value="<?= $submittedData['employee_name'] ?>" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-red-200" placeholder="Enter full name" />
                        </div>
                        <div>
                            <label for="employee_id" class="block text-sm font-medium mb-1">Employee ID</label>
                            <input id="employee_id" name="employee_id" type="text" value="<?= $submittedData['employee_id'] ?>" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-red-200" placeholder="EMP-0001" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="designation" class="block text-sm font-medium mb-1">Designation</label>
                            <input id="designation" name="designation" type="text" value="<?= $submittedData['designation'] ?>" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-red-200" />
                        </div>
                        <div>
                            <label for="department" class="block text-sm font-medium mb-1">Department</label>
                            <input id="department" name="department" type="text" value="<?= $submittedData['department'] ?>" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-red-200" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="phone" class="block text-sm font-medium mb-1">Phone Number</label>
                            <input id="phone" name="phone" type="tel" value="<?= $submittedData['phone'] ?>" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-red-200" />
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium mb-1">Email</label>
                            <input id="email" name="email" type="email" value="<?= $submittedData['email'] ?>" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-red-200" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="blood_group" class="block text-sm font-medium mb-1">Blood Group</label>
                            <input id="blood_group" name="blood_group" type="text" value="<?= $submittedData['blood_group'] ?>" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-red-200" />
                        </div>
                        <div>
                            <label for="address" class="block text-sm font-medium mb-1">Address</label>
                            <input id="address" name="address" type="text" value="<?= $submittedData['address'] ?>" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-red-200" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="joining_date" class="block text-sm font-medium mb-1">Date of Joining</label>
                            <input id="joining_date" name="joining_date" type="date" value="<?= $submittedData['joining_date'] ?>" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-red-200" />
                        </div>
                        <div>
                            <label for="expiry_date" class="block text-sm font-medium mb-1">Expiry Date (Optional)</label>
                            <input id="expiry_date" name="expiry_date" type="date" value="<?= $submittedData['expiry_date'] ?>" class="w-full border rounded-lg p-2.5 focus:ring-2 focus:ring-red-200" />
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-red-600 text-white px-4 py-2.5 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300">Save (PHP Submit)</button>
                        <button type="button" id="downloadPng" class="inline-flex items-center rounded-lg bg-slate-700 text-white px-4 py-2.5 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300">Download PNG</button>
                        <button type="button" id="downloadJpg" class="inline-flex items-center rounded-lg bg-slate-700 text-white px-4 py-2.5 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300">Download JPG</button>
                        <button type="button" id="downloadPdf" class="inline-flex items-center rounded-lg bg-slate-700 text-white px-4 py-2.5 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300">Download PDF</button>
                    </div>
                </form>
            </section>

            <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 md:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Live Preview</h2>
                    <span class="text-xs text-slate-500">Front Side</span>
                </div>

                <div class="flex justify-center">
                    <article id="idCard" class="id-card-gradient w-[340px] max-w-full rounded-2xl shadow-lg overflow-hidden border border-red-100" aria-label="Employee ID Card Preview">
                        <div class="red-accent text-white p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div id="logoFallback" class="h-12 w-12 rounded-full bg-white text-red-700 font-bold text-sm flex items-center justify-center border border-red-100">LOGO</div>
                                    <img id="logoPreview" src="<?= $storedUploads['company_logo'] ? clean_input($storedUploads['company_logo']) : '' ?>" alt="Company Logo" class="h-12 w-12 rounded-full object-cover border border-red-100 hidden" />
                                    <div>
                                        <p class="font-semibold tracking-wide">Your Company</p>
                                        <p class="text-xs opacity-90">Employee Identity Card</p>
                                    </div>
                                </div>
                                <span class="text-xs bg-white/20 px-2 py-1 rounded-full">Valid ID</span>
                            </div>
                        </div>

                        <div class="p-4 space-y-4">
                            <div class="flex gap-4 items-center">
                                <img id="photoPreview" src="<?= $storedUploads['employee_photo'] ? clean_input($storedUploads['employee_photo']) : '' ?>" alt="Employee" class="h-24 w-24 rounded-xl object-cover border-2 border-red-300 <?= $storedUploads['employee_photo'] ? '' : 'hidden' ?>" />
                                <div id="photoFallback" class="h-24 w-24 rounded-xl border-2 border-dashed border-red-300 flex items-center justify-center text-xs text-red-500 font-medium <?= $storedUploads['employee_photo'] ? 'hidden' : '' ?>">Photo</div>
                                <div>
                                    <h3 id="preview_name" class="text-xl font-bold text-red-700 leading-tight"><?= $submittedData['employee_name'] ?></h3>
                                    <p id="preview_designation" class="text-sm font-medium text-slate-700"><?= $submittedData['designation'] ?></p>
                                    <p id="preview_department" class="text-xs text-slate-500 mt-1"><?= $submittedData['department'] ?></p>
                                </div>
                            </div>

                            <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                <div><dt class="text-slate-500">Employee ID</dt><dd id="preview_employee_id" class="font-semibold text-slate-800"><?= $submittedData['employee_id'] ?></dd></div>
                                <div><dt class="text-slate-500">Blood Group</dt><dd id="preview_blood_group" class="font-semibold text-slate-800"><?= $submittedData['blood_group'] ?></dd></div>
                                <div><dt class="text-slate-500">Phone</dt><dd id="preview_phone" class="font-semibold text-slate-800"><?= $submittedData['phone'] ?></dd></div>
                                <div><dt class="text-slate-500">Email</dt><dd id="preview_email" class="font-semibold text-slate-800 break-all"><?= $submittedData['email'] ?></dd></div>
                                <div class="col-span-2"><dt class="text-slate-500">Address</dt><dd id="preview_address" class="font-semibold text-slate-800"><?= $submittedData['address'] ?></dd></div>
                            </dl>

                            <div class="text-xs text-slate-600 border-t pt-3 grid grid-cols-2 gap-3">
                                <p>Join: <span id="preview_joining_date" class="font-semibold text-slate-800"><?= $submittedData['joining_date'] ?></span></p>
                                <p>Expiry: <span id="preview_expiry_date" class="font-semibold text-slate-800"><?= $submittedData['expiry_date'] ?: 'N/A' ?></span></p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <section class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-800">Local Run Instructions</h3>
            <ol class="list-decimal pl-5 mt-2 text-sm text-slate-700 space-y-1">
                <li>Open terminal in this folder.</li>
                <li>Run: <code class="bg-slate-100 px-1 rounded">php -S localhost:8000</code></li>
                <li>Visit: <code class="bg-slate-100 px-1 rounded">http://localhost:8000/index.php</code></li>
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

        const fieldMappings = [
            ['employee_name', 'preview_name'],
            ['employee_id', 'preview_employee_id'],
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

        function updatePreviewField(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            if (!input || !preview) return;

            const value = input.value.trim();
            preview.textContent = value || placeholders[inputId] || '-';
        }

        function showFileError(fieldId, message = '') {
            const errorEl = document.getElementById(`${fieldId}_error`);
            if (!errorEl) return;

            if (message) {
                errorEl.textContent = message;
                errorEl.classList.remove('hidden');
            } else {
                errorEl.textContent = '';
                errorEl.classList.add('hidden');
            }
        }

        function validateImage(file) {
            if (!ALLOWED_TYPES.includes(file.type)) {
                return 'Please upload JPG, PNG, or WEBP image.';
            }
            if (file.size > MAX_IMAGE_SIZE) {
                return 'Image size must be 5MB or less.';
            }
            return '';
        }

        function previewImage(inputId, imageId, fallbackId) {
            const input = document.getElementById(inputId);
            const imageEl = document.getElementById(imageId);
            const fallbackEl = document.getElementById(fallbackId);
            if (!input || !imageEl || !fallbackEl) return;

            input.addEventListener('change', (event) => {
                const file = event.target.files?.[0];
                if (!file) {
                    imageEl.src = '';
                    imageEl.classList.add('hidden');
                    fallbackEl.classList.remove('hidden');
                    showFileError(inputId, '');
                    return;
                }

                const validationError = validateImage(file);
                if (validationError) {
                    input.value = '';
                    imageEl.src = '';
                    imageEl.classList.add('hidden');
                    fallbackEl.classList.remove('hidden');
                    showFileError(inputId, validationError);
                    return;
                }

                showFileError(inputId, '');
                const reader = new FileReader();
                reader.onload = (e) => {
                    imageEl.src = e.target?.result || '';
                    imageEl.classList.remove('hidden');
                    fallbackEl.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            });
        }

        async function captureCardCanvas() {
            const card = document.getElementById('idCard');
            return html2canvas(card, {
                scale: 3,
                useCORS: true,
                backgroundColor: '#ffffff'
            });
        }

        async function downloadImage(format = 'png') {
            try {
                const canvas = await captureCardCanvas();
                const mime = format === 'jpg' ? 'image/jpeg' : 'image/png';
                const data = canvas.toDataURL(mime, 1.0);
                const link = document.createElement('a');
                link.href = data;
                link.download = `employee-id-card.${format}`;
                link.click();
            } catch (error) {
                alert('Unable to export image. Please try again.');
                console.error(error);
            }
        }

        async function downloadPdf() {
            try {
                const canvas = await captureCardCanvas();
                const imgData = canvas.toDataURL('image/png', 1.0);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({ orientation: 'landscape', unit: 'pt', format: [canvas.width, canvas.height] });
                pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
                pdf.save('employee-id-card.pdf');
            } catch (error) {
                alert('Unable to export PDF. Please try again.');
                console.error(error);
            }
        }

        fieldMappings.forEach(([inputId, previewId]) => {
            const input = document.getElementById(inputId);
            if (!input) return;

            updatePreviewField(inputId, previewId);
            input.addEventListener('input', () => updatePreviewField(inputId, previewId));
            input.addEventListener('change', () => updatePreviewField(inputId, previewId));
        });

        previewImage('company_logo', 'logoPreview', 'logoFallback');
        previewImage('employee_photo', 'photoPreview', 'photoFallback');

        document.getElementById('downloadPng').addEventListener('click', () => downloadImage('png'));
        document.getElementById('downloadJpg').addEventListener('click', () => downloadImage('jpg'));
        document.getElementById('downloadPdf').addEventListener('click', downloadPdf);
    </script>
</body>
</html>
