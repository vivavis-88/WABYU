<?php
declare(strict_types=1);

final class WabyuValidationException extends RuntimeException {}

function wabyu_clean_text(mixed $v, int $max): string
{
    $s = is_string($v) ? $v : '';
    $s = preg_replace('/[^\P{C}\n\t]/u', '', str_replace("\r\n", "\n", $s)) ?? '';
    return mb_substr(trim($s), 0, $max);
}

/**
 * @param bool $partial when true only keys present in $in are validated/returned (used for edits)
 * @return array{0:array<string,mixed>,1:array<string,string>} [clean, errors]
 */
function wabyu_validate_memory_input(array $in, bool $partial = false): array
{
    $clean = []; $errors = [];
    if (!$partial || array_key_exists('title', $in)) {
        $t = wabyu_clean_text($in['title'] ?? '', 120);
        if ($t === '') { $errors['title'] = 'Please give this memory a title.'; }
        $clean['title'] = $t;
    }
    if (!$partial || array_key_exists('comment', $in)) {
        $raw = is_string($in['comment'] ?? '') ? (string)($in['comment'] ?? '') : '';
        if (mb_strlen($raw) > 2000) { $errors['comment'] = 'The dedication is limited to 2000 characters.'; }
        $clean['comment'] = wabyu_clean_text($raw, 2000);
    }
    if (!$partial || array_key_exists('category', $in)) {
        $clean['category'] = wabyu_clean_text($in['category'] ?? '', 40);
    }
    if (!$partial || array_key_exists('memory_date', $in)) {
        $d = is_string($in['memory_date'] ?? '') ? trim((string)($in['memory_date'] ?? '')) : '';
        if ($d !== '') {
            $dt = DateTime::createFromFormat('!Y-m-d', $d);
            if (!$dt || $dt->format('Y-m-d') !== $d) { $errors['memory_date'] = 'Please use a valid date.'; $d = ''; }
        }
        $clean['memory_date'] = $d;
    }
    if (!$partial || array_key_exists('favorite', $in)) {
        $f = $in['favorite'] ?? false;
        $clean['favorite'] = $f === true || $f === 1 || in_array(strtolower((string)$f), ['1', 'true', 'on', 'yes'], true);
    }
    return [$clean, $errors];
}

/** Validates an uploaded image from $_FILES and returns the safe extension. */
function wabyu_validate_upload(array $file): string
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) {
        throw new WabyuValidationException(match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That photograph is larger than the server allows.',
            UPLOAD_ERR_NO_FILE => 'Please choose a photograph.',
            default => 'The upload did not complete. Please try again.',
        });
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) { throw new WabyuValidationException('Invalid upload.'); }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > WABYU_MAX_IMAGE_BYTES) {
        throw new WabyuValidationException('Photographs must be under ' . (WABYU_MAX_IMAGE_BYTES / 1048576) . ' MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!is_string($mime) || !isset(WABYU_ALLOWED_MIME[$mime])) {
        throw new WabyuValidationException('Only JPEG, PNG, and WebP photographs are supported.');
    }
    $info = @getimagesize($tmp);
    if ($info === false || $info[0] < 1 || $info[1] < 1 || ($info['mime'] ?? '') !== $mime) {
        throw new WabyuValidationException('That file does not appear to be a valid image.');
    }
    if ($info[0] * $info[1] > 100000000) { throw new WabyuValidationException('That image is too large in dimensions.'); }
    return WABYU_ALLOWED_MIME[$mime];
}
