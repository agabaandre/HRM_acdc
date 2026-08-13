<?php

namespace Modules\Auth\Services;

use App\Support\StaffPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\PortalUser;
use Modules\Staff\Services\StaffProfileService;
use Staff\Shared\StaffStorage;

class SelfServiceProfileService
{
    public function __construct(
        protected StaffProfileService $staffProfiles,
    ) {}

    public function passwordLoginAvailable(PortalUser $user): bool
    {
        return (bool) $user->allow_email_login && (bool) config('auth.allow_alternative_login', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(PortalUser $user): array
    {
        $staffId = $this->requireStaffId($user);
        $staff = $this->staffProfiles->find($staffId);
        if ($staff === null) {
            abort(404, 'Staff profile not found.');
        }

        $contracts = $this->staffProfiles->contracts($staffId);
        $contract = $contracts[0] ?? null;

        $kinTypes = DB::table('kin_relationship_types')
            ->orderBy('relationship_name')
            ->get(['kin_relationship_id as id', 'relationship_name as name'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->all();

        $passport = trim((string) ($staff->passport_biodata_page ?? ''));
        $signature = trim((string) ($staff->signature ?? ''));
        $photo = trim((string) ($staff->photo ?? ''));

        return [
            'staff' => $this->staffPayload($staff),
            'contract' => $contract ? $this->contractPayload($contract) : null,
            'supervisors' => [
                'first' => $contract ? [
                    'name' => trim((string) ($contract->first_supervisor_name ?? '')),
                ] : null,
                'second' => $contract ? [
                    'name' => trim((string) ($contract->second_supervisor_name ?? '')),
                ] : null,
            ],
            'media' => [
                'photo_url' => StaffPhoto::url($photo !== '' ? $photo : null),
                'signature_url' => $this->signatureUrl($signature !== '' ? $signature : null),
                'passport_url' => $this->passportUrl($passport !== '' ? $passport : null),
                'passport_is_pdf' => $passport !== '' && strtolower(pathinfo($passport, PATHINFO_EXTENSION)) === 'pdf',
            ],
            'lookups' => [
                'kin_relationship_types' => $kinTypes,
            ],
            'flags' => [
                'allow_email_login' => (bool) $user->allow_email_login,
                'password_login_available' => $this->passwordLoginAvailable($user),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(PortalUser $user, array $data): array
    {
        $staffId = $this->requireStaffId($user);
        $validated = $this->validateEditable($data);
        $nok = $this->normalizeNextOfKin($validated['next_of_kin'] ?? []);

        DB::table('staff')->where('staff_id', $staffId)->update([
            'private_email' => $validated['private_email'],
            'whatsapp' => $validated['whatsapp'] ?? null,
            'tel_1' => $validated['tel_1'],
            'tel_2' => $validated['tel_2'] ?? null,
            'langauge' => $validated['langauge'] ?? null,
            'residential_address_duty_station' => $validated['residential_address_duty_station'],
            'number_of_dependants' => $validated['number_of_dependants'],
            'next_of_kin_json' => json_encode($nok, JSON_UNESCAPED_UNICODE),
        ]);

        return $this->show($user);
    }

    public function storePhoto(PortalUser $user, UploadedFile $file): array
    {
        $staffId = $this->requireStaffId($user);
        $this->assertImageUpload($file, 2048);
        $filename = $this->safeBaseName($user).'_'.time().'.'.$this->extension($file, 'jpg');
        $dir = StaffStorage::ciPath('staff');
        $this->storeUploadedFile($file, $dir, $filename);
        DB::table('staff')->where('staff_id', $staffId)->update(['photo' => $filename]);

        return $this->show($user);
    }

    public function storePassport(PortalUser $user, UploadedFile $file): array
    {
        $staffId = $this->requireStaffId($user);
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $ok = str_starts_with($mime, 'image/')
            || $mime === 'application/pdf'
            || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'], true);
        if (! $ok || $file->getSize() > 4096 * 1024) {
            throw ValidationException::withMessages([
                'passport' => ['Passport biodata must be an image or PDF up to 4MB.'],
            ]);
        }
        if ($ext === '') {
            $ext = $mime === 'application/pdf' ? 'pdf' : 'jpg';
        }
        $filename = $this->safeBaseName($user).'_passport_'.time().'.'.$ext;
        $dir = StaffStorage::ciPath('staff/passport_biodata');
        $this->storeUploadedFile($file, $dir, $filename);
        DB::table('staff')->where('staff_id', $staffId)->update(['passport_biodata_page' => $filename]);

        return $this->show($user);
    }

    public function storeSignatureFile(PortalUser $user, UploadedFile $file): array
    {
        $this->assertImageUpload($file, 2048);
        $staffId = $this->requireStaffId($user);
        $dir = StaffStorage::ciPath('staff/signature');
        $tmpName = $this->safeBaseName($user).'_sig_tmp_'.time().'.'.$this->extension($file, 'png');
        $this->storeUploadedFile($file, $dir, $tmpName);
        $path = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$tmpName;
        $pngName = $this->safeBaseName($user).'_sig_'.time().'.png';
        $pngPath = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$pngName;
        $converted = $this->convertImageFileToPng($path, $pngPath);
        if ($converted) {
            @unlink($path);
            $filename = $pngName;
        } else {
            $filename = $tmpName;
        }
        DB::table('staff')->where('staff_id', $staffId)->update(['signature' => $filename]);

        return $this->show($user);
    }

    public function storeSignatureDataUrl(PortalUser $user, string $dataUrl): array
    {
        $staffId = $this->requireStaffId($user);
        $filename = $this->saveSignatureDataUrl($dataUrl, $this->safeBaseName($user));
        if ($filename === null) {
            throw ValidationException::withMessages([
                'data_url' => ['Could not save drawn signature. Try again or upload an image file.'],
            ]);
        }
        DB::table('staff')->where('staff_id', $staffId)->update(['signature' => $filename]);

        return $this->show($user);
    }

    /**
     * @param  array{current_password: string, password: string}  $data
     */
    public function changePassword(PortalUser $user, array $data): void
    {
        if (! $this->passwordLoginAvailable($user)) {
            abort(403, 'Email and password sign-in is not enabled for your account.');
        }

        if (! $user->password || ! password_verify($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        DB::table('user')->where('user_id', $user->user_id)->update([
            'password' => password_hash($data['password'], PASSWORD_ARGON2ID),
        ]);
    }

    protected function requireStaffId(PortalUser $user): int
    {
        $staffId = (int) ($user->auth_staff_id ?? 0);
        if ($staffId < 1) {
            abort(422, 'Your account is not linked to a staff profile.');
        }

        return $staffId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validateEditable(array $data): array
    {
        $validator = validator($data, [
            'private_email' => ['required', 'email', 'max:190'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'tel_1' => ['required', 'string', 'max:50'],
            'tel_2' => ['nullable', 'string', 'max:50'],
            'langauge' => ['nullable', 'in:en,fr,sw,ar'],
            'residential_address_duty_station' => ['required', 'string', 'max:500'],
            'number_of_dependants' => ['required', 'integer', 'min:0', 'max:99'],
            'next_of_kin' => ['required', 'array', 'max:2'],
            'next_of_kin.*.name' => ['nullable', 'string', 'max:190'],
            'next_of_kin.*.relationship_id' => ['nullable', 'integer', 'min:0'],
            'next_of_kin.*.phone' => ['nullable', 'string', 'max:50'],
            'next_of_kin.*.email' => ['nullable', 'email', 'max:190'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $this->assertNextOfKin($validated['next_of_kin'] ?? []);

        return $validated;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function assertNextOfKin(array $rows): void
    {
        $r0 = $rows[0] ?? [];
        $n0 = trim((string) ($r0['name'] ?? ''));
        $rid0 = (int) ($r0['relationship_id'] ?? 0);
        $ph0 = trim((string) ($r0['phone'] ?? ''));
        $em0 = trim((string) ($r0['email'] ?? ''));

        if ($n0 === '' || $rid0 <= 0) {
            throw ValidationException::withMessages([
                'next_of_kin.0' => ['Next of kin (first): please enter full name and select a relationship.'],
            ]);
        }
        if ($ph0 === '') {
            throw ValidationException::withMessages([
                'next_of_kin.0.phone' => ['Next of kin (first): phone number is required.'],
            ]);
        }
        if ($em0 === '') {
            throw ValidationException::withMessages([
                'next_of_kin.0.email' => ['Next of kin (first): email is required.'],
            ]);
        }

        $r1 = $rows[1] ?? [];
        $n1 = trim((string) ($r1['name'] ?? ''));
        $rid1 = (int) ($r1['relationship_id'] ?? 0);
        $ph1 = trim((string) ($r1['phone'] ?? ''));
        $em1 = trim((string) ($r1['email'] ?? ''));
        $any1 = $n1 !== '' || $rid1 > 0 || $ph1 !== '' || $em1 !== '';
        if (! $any1) {
            return;
        }
        if ($n1 === '' || $rid1 <= 0) {
            throw ValidationException::withMessages([
                'next_of_kin.1' => ['Next of kin (second): enter full name and relationship, or clear the row.'],
            ]);
        }
        if ($ph1 === '') {
            throw ValidationException::withMessages([
                'next_of_kin.1.phone' => ['Next of kin (second): phone number is required when the row is used.'],
            ]);
        }
        if ($em1 === '') {
            throw ValidationException::withMessages([
                'next_of_kin.1.email' => ['Next of kin (second): email is required when the row is used.'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{name: string, relationship_id: int, phone: string, email: string}>
     */
    protected function normalizeNextOfKin(array $rows): array
    {
        $out = [];
        foreach (array_slice(array_values($rows), 0, 2) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $rid = (int) ($row['relationship_id'] ?? 0);
            $phone = trim((string) ($row['phone'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            if ($name === '' && $rid <= 0 && $phone === '' && $email === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'relationship_id' => $rid,
                'phone' => $phone,
                'email' => $email,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    protected function staffPayload(object $staff): array
    {
        $nok = json_decode((string) ($staff->next_of_kin_json ?? '[]'), true);
        if (! is_array($nok)) {
            $nok = [];
        }
        while (count($nok) < 2) {
            $nok[] = ['name' => '', 'relationship_id' => '', 'phone' => '', 'email' => ''];
        }
        $nok = array_slice(array_values($nok), 0, 2);

        return [
            'staff_id' => (int) $staff->staff_id,
            'SAPNO' => $staff->SAPNO ?? null,
            'title' => $staff->title ?? null,
            'fname' => $staff->fname ?? null,
            'lname' => $staff->lname ?? null,
            'oname' => $staff->oname ?? null,
            'gender' => $staff->gender ?? null,
            'date_of_birth' => $staff->date_of_birth ?? null,
            'nationality' => $staff->nationality ?? null,
            'region_name' => $staff->region_name ?? null,
            'work_email' => $staff->work_email ?? null,
            'private_email' => $staff->private_email ?? null,
            'whatsapp' => $staff->whatsapp ?? null,
            'tel_1' => $staff->tel_1 ?? null,
            'tel_2' => $staff->tel_2 ?? null,
            'langauge' => $staff->langauge ?? null,
            'physical_location' => $staff->physical_location ?? null,
            'residential_address_duty_station' => $staff->residential_address_duty_station ?? null,
            'number_of_dependants' => $staff->number_of_dependants !== null ? (int) $staff->number_of_dependants : null,
            'initiation_date' => $staff->initiation_date ?? null,
            'photo' => $staff->photo ?? null,
            'signature' => $staff->signature ?? null,
            'passport_biodata_page' => $staff->passport_biodata_page ?? null,
            'next_of_kin' => $nok,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function contractPayload(object $contract): array
    {
        return [
            'job_name' => $contract->job_name ?? null,
            'job_acting' => $contract->job_acting ?? null,
            'division_name' => $contract->division_name ?? null,
            'duty_station_name' => $contract->duty_station_name ?? null,
            'grade' => $contract->grade ?? null,
            'contract_type' => $contract->contract_type ?? null,
            'contracting_institution' => $contract->contracting_institution ?? null,
            'funder' => $contract->funder ?? null,
            'start_date' => $contract->start_date ?? null,
            'end_date' => $contract->end_date ?? null,
            'status_label' => $contract->status_label ?? null,
        ];
    }

    protected function signatureUrl(?string $filename): ?string
    {
        if ($filename === null || trim($filename) === '') {
            return null;
        }
        $path = StaffStorage::ciPath('staff/signature/'.basename($filename));
        if (! is_file($path)) {
            return null;
        }

        return route('staff.media.signature', ['filename' => basename($filename)]);
    }

    protected function passportUrl(?string $filename): ?string
    {
        if ($filename === null || trim($filename) === '') {
            return null;
        }
        $path = StaffStorage::ciPath('staff/passport_biodata/'.basename($filename));
        if (! is_file($path)) {
            return null;
        }

        return route('staff.media.passport', ['filename' => basename($filename)]);
    }

    protected function safeBaseName(PortalUser $user): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-.]/', '', str_replace(' ', '_', (string) $user->name)) ?: 'staff';

        return substr((string) $name, 0, 40);
    }

    protected function extension(UploadedFile $file, string $fallback): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());

        return $ext !== '' ? $ext : $fallback;
    }

    protected function assertImageUpload(UploadedFile $file, int $maxKb): void
    {
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $okMime = str_starts_with($mime, 'image/') || $mime === 'application/octet-stream';
        $okExt = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', ''], true);
        if ((! $okMime && ! $okExt) || $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => ["Please upload a JPG/PNG/GIF/WebP image under {$maxKb}KB."],
            ]);
        }
    }

    protected function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (! is_dir($dir) || ! is_writable($dir)) {
            throw ValidationException::withMessages([
                'file' => ['Upload directory is not writable. Contact an administrator.'],
            ]);
        }
    }

    protected function storeUploadedFile(UploadedFile $file, string $dir, string $filename): void
    {
        $this->ensureDir($dir);
        $target = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$filename;
        $source = $file->getRealPath();
        if (! is_string($source) || $source === '') {
            throw ValidationException::withMessages([
                'file' => ['Could not read the uploaded file.'],
            ]);
        }
        if (@copy($source, $target)) {
            @chmod($target, 0644);

            return;
        }
        try {
            $file->move($dir, $filename);
            @chmod($target, 0644);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => ['Could not save the uploaded file. Please try again.'],
            ]);
        }
    }

    protected function convertImageFileToPng(string $source, string $dest): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }
        $bin = @file_get_contents($source);
        if ($bin === false) {
            return false;
        }
        $img = @imagecreatefromstring($bin);
        if ($img === false) {
            return false;
        }
        imagesavealpha($img, true);
        imagealphablending($img, false);
        $ok = imagepng($img, $dest, 9);
        imagedestroy($img);

        return (bool) $ok;
    }

    protected function saveSignatureDataUrl(string $dataUrl, string $safeName): ?string
    {
        $dataUrl = trim($dataUrl);
        if ($dataUrl === '') {
            return null;
        }

        $bin = null;
        if (preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $dataUrl)) {
            $comma = strpos($dataUrl, ',');
            if ($comma === false) {
                return null;
            }
            $bin = base64_decode(substr($dataUrl, $comma + 1), true);
        } elseif (preg_match('#^[A-Za-z0-9+/=\r\n]+$#', $dataUrl)) {
            $bin = base64_decode(preg_replace('/\s+/', '', $dataUrl) ?? '', true);
        }

        if ($bin === false || $bin === null || strlen($bin) < 16 || strlen($bin) > 1024 * 1024) {
            return null;
        }

        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($bin);
            if ($img !== false) {
                imagesavealpha($img, true);
                imagealphablending($img, false);
                ob_start();
                imagepng($img, null, 9);
                $png = ob_get_clean();
                imagedestroy($img);
                if (is_string($png) && $png !== '') {
                    $bin = $png;
                }
            }
        }

        $dir = StaffStorage::ciPath('staff/signature');
        $this->ensureDir($dir);
        $filename = ($safeName !== '' ? $safeName : 'staff_sig').'_sig_'.time().'.png';
        $path = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$filename;
        if (@file_put_contents($path, $bin) === false) {
            return null;
        }

        return $filename;
    }
}
