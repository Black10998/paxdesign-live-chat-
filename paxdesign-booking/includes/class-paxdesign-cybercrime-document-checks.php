<?php
/**
 * Preliminary document and evidence quality checks for Cybercrime Support.
 *
 * These checks are automated quality gates used by major intake platforms.
 * They are NOT legal identity verification, forensic analysis, or a final
 * authenticity decision. Uncertain, inconsistent, or suspicious results
 * are flagged for administrator review.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Cybercrime_Document_Checks {

    const STATUS_PASS   = 'pass';
    const STATUS_FAIL   = 'fail';
    const STATUS_REVIEW = 'review';

    const MIN_IMAGE_SHORT_SIDE = 400;
    const MIN_FILE_BYTES       = 2048;
    const MIN_PDF_BYTES        = 1500;

    /** @var list<string> */
    private static $identity_ext = array('jpg', 'jpeg', 'png', 'pdf', 'heic', 'heif', 'webp');

    /** @var list<string> */
    private static $blocked_ext = array('exe', 'bat', 'cmd', 'com', 'scr', 'js', 'php', 'phtml', 'html', 'htm', 'svg', 'sh', 'dll');

    /**
     * Evaluate already-stored uploads (after wp_handle_upload).
     *
     * @param array<int, array<string, mixed>> $uploads
     * @param array<string, mixed>             $context reporter_name, email, category, existing_hashes
     * @return array<string, mixed>
     */
    public static function evaluate_uploads($uploads, $context = array()) {
        $uploads = is_array($uploads) ? $uploads : array();
        $context = is_array($context) ? $context : array();
        $results = array();
        $seen_hashes = array();
        $existing = array();
        if (!empty($context['existing_hashes']) && is_array($context['existing_hashes'])) {
            foreach ($context['existing_hashes'] as $hash) {
                $hash = strtolower(preg_replace('/[^a-f0-9]/', '', (string) $hash));
                if (strlen($hash) === 64) {
                    $existing[$hash] = true;
                }
            }
        }

        foreach ($uploads as $index => $file) {
            if (!is_array($file)) {
                continue;
            }
            $check = self::evaluate_stored_file($file, $context, $seen_hashes, $existing);
            $check['index'] = (int) $index;
            $results[] = $check;
            if (!empty($check['sha256'])) {
                $seen_hashes[strtolower((string) $check['sha256'])] = true;
            }
        }

        return self::summarize($results, $context);
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $context
     * @param array<string, bool>  $batch_hashes
     * @param array<string, bool>  $existing_hashes
     * @return array<string, mixed>
     */
    public static function evaluate_stored_file($file, $context = array(), $batch_hashes = array(), $existing_hashes = array()) {
        $field = sanitize_key((string) ($file['field'] ?? ''));
        $name  = (string) ($file['original_name'] ?? $file['name'] ?? '');
        $path  = (string) ($file['path'] ?? '');
        $mime  = strtolower((string) ($file['type'] ?? ''));
        $size  = (int) ($file['size'] ?? 0);
        if ($size <= 0 && $path !== '' && is_file($path)) {
            $size = (int) filesize($path);
        }

        $issues = array();
        $corrections = array();
        $review = array();
        $status = self::STATUS_PASS;
        $sha256 = '';
        $expiry = '';
        $appears_expired = false;
        $readable = true;
        $complete = true;
        $type_ok = true;

        if ($path !== '' && is_readable($path)) {
            $sha256 = hash_file('sha256', $path);
        } elseif (!empty($file['sha256'])) {
            $sha256 = strtolower((string) $file['sha256']);
        }

        if ($size <= 0) {
            $readable = false;
            $complete = false;
            $status = self::STATUS_FAIL;
            $issues[] = 'empty_file';
            $corrections[] = __('The file is empty. Please upload a complete scan or photo.', 'paxdesign-booking');
        } elseif ($size < self::MIN_FILE_BYTES) {
            $complete = false;
            $status = self::STATUS_FAIL;
            $issues[] = 'file_too_small';
            $corrections[] = __('The file is too small to be a readable document. Please upload a clearer original.', 'paxdesign-booking');
        }

        $ext = strtolower(pathinfo($name !== '' ? $name : (string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (in_array($ext, self::$blocked_ext, true)) {
            $type_ok = false;
            $status = self::STATUS_FAIL;
            $issues[] = 'blocked_type';
            $corrections[] = __('This file type is not accepted. Please upload a PDF or image of the document.', 'paxdesign-booking');
        }

        $is_identity = ($field === 'identity_document');
        if ($is_identity && $ext !== '' && !in_array($ext, self::$identity_ext, true)) {
            $type_ok = false;
            $status = self::STATUS_FAIL;
            $issues[] = 'wrong_identity_type';
            $corrections[] = __('Identity documents must be a PDF or photo (JPG, PNG, HEIC).', 'paxdesign-booking');
        }

        if ($sha256 !== '') {
            $sha256 = strtolower($sha256);
            if (!empty($batch_hashes[$sha256])) {
                $status = self::STATUS_FAIL;
                $issues[] = 'duplicate_in_submission';
                $corrections[] = __('This file was uploaded more than once in the same submission. Please remove the duplicate.', 'paxdesign-booking');
            }
            if (!empty($existing_hashes[$sha256])) {
                $issues[] = 'duplicate_existing';
                $review[] = 'duplicate_of_previously_submitted_file';
                if ($status !== self::STATUS_FAIL) {
                    $status = self::STATUS_REVIEW;
                }
            }
        }

        if ($path !== '' && is_readable($path)) {
            if (self::is_image_mime($mime, $ext)) {
                $image = @getimagesize($path);
                if ($image === false) {
                    if (in_array($ext, array('heic', 'heif'), true)) {
                        $review[] = 'heic_unreadable_without_decoder';
                        if ($status === self::STATUS_PASS) {
                            $status = self::STATUS_REVIEW;
                        }
                    } else {
                        $readable = false;
                        $status = self::STATUS_FAIL;
                        $issues[] = 'unreadable_image';
                        $corrections[] = __('The image could not be opened. Please upload a clear JPG, PNG, or PDF.', 'paxdesign-booking');
                    }
                } else {
                    $width = (int) ($image[0] ?? 0);
                    $height = (int) ($image[1] ?? 0);
                    $short = min($width, $height);
                    if ($width < 50 || $height < 50) {
                        $readable = false;
                        $complete = false;
                        $status = self::STATUS_FAIL;
                        $issues[] = 'image_too_tiny';
                        $corrections[] = __('The image is too small to read. Please photograph the full document.', 'paxdesign-booking');
                    } elseif ($short < self::MIN_IMAGE_SHORT_SIDE) {
                        $complete = false;
                        $issues[] = 'low_resolution';
                        $corrections[] = __('The image resolution is low. Please retake the photo so all text is readable.', 'paxdesign-booking');
                        if ($status === self::STATUS_PASS) {
                            $status = self::STATUS_REVIEW;
                        }
                        $review[] = 'low_resolution_identity_or_evidence';
                    }
                    $brightness = self::estimate_image_readability($path, $width, $height);
                    if ($brightness === 'too_dark' || $brightness === 'too_bright' || $brightness === 'low_contrast') {
                        $issues[] = 'poor_image_quality_' . $brightness;
                        $corrections[] = __('The photo looks too dark, bright, or blurry. Please retake it in even lighting with the full document in frame.', 'paxdesign-booking');
                        $review[] = 'image_quality_uncertain';
                        if ($status === self::STATUS_PASS) {
                            $status = self::STATUS_REVIEW;
                        }
                    }
                    $exif_date = self::read_exif_datetime($path);
                    if ($exif_date !== '') {
                        $review[] = 'exif_capture_date_present';
                    }
                }
            } elseif ($ext === 'pdf' || strpos($mime, 'pdf') !== false) {
                $header = (string) @file_get_contents($path, false, null, 0, 8);
                if (strpos($header, '%PDF') !== 0) {
                    $readable = false;
                    $type_ok = false;
                    $status = self::STATUS_FAIL;
                    $issues[] = 'invalid_pdf';
                    $corrections[] = __('The PDF could not be read. Please export a valid PDF or upload a photo of the document.', 'paxdesign-booking');
                } elseif ($size < self::MIN_PDF_BYTES) {
                    $complete = false;
                    $status = self::STATUS_FAIL;
                    $issues[] = 'pdf_too_small';
                    $corrections[] = __('The PDF appears incomplete. Please upload the full document.', 'paxdesign-booking');
                }
            }
        } elseif ($path === '') {
            $review[] = 'stored_path_unavailable_for_deeper_checks';
            if ($status === self::STATUS_PASS) {
                $status = self::STATUS_REVIEW;
            }
        }

        $parsed_expiry = self::extract_possible_expiry($name, $path);
        if (!empty($parsed_expiry['date'])) {
            $expiry = (string) $parsed_expiry['date'];
            if (!empty($parsed_expiry['expired'])) {
                $appears_expired = true;
                $issues[] = 'appears_expired';
                $corrections[] = __('This document appears to be expired. Please upload a currently valid identity document.', 'paxdesign-booking');
                $review[] = 'expiry_date_requires_human_confirmation';
                if ($is_identity) {
                    $status = self::STATUS_FAIL;
                } elseif ($status === self::STATUS_PASS) {
                    $status = self::STATUS_REVIEW;
                }
            } else {
                $review[] = 'possible_validity_date_detected';
            }
        } elseif ($is_identity) {
            $review[] = 'expiry_not_machine_readable';
        }

        $name_match = self::name_matches_filename((string) ($context['reporter_name'] ?? ''), $name);
        if ($is_identity && $name_match === 'mismatch') {
            $issues[] = 'name_not_found_on_filename';
            $review[] = 'entered_name_could_not_be_matched_automatically';
            if ($status === self::STATUS_PASS) {
                $status = self::STATUS_REVIEW;
            }
        }

        if ($is_identity) {
            $review[] = 'identity_authenticity_requires_human_review';
            if ($status === self::STATUS_PASS) {
                $status = self::STATUS_REVIEW;
            }
        }

        $required_fields = self::required_fields_for_field($field);
        if ($is_identity && !$complete) {
            $issues[] = 'required_fields_possibly_missing';
        }

        $customer_status = $status;
        if ($status === self::STATUS_FAIL) {
            $customer_status = 'rejected';
        } elseif ($status === self::STATUS_REVIEW) {
            $customer_status = 'pending_review';
        } else {
            $customer_status = 'accepted_for_review';
        }

        return array(
            'field'                 => $field,
            'filename'              => basename($name !== '' ? $name : (string) ($file['name'] ?? 'file')),
            'stored_name'           => (string) ($file['name'] ?? ''),
            'mime'                  => $mime,
            'size'                  => $size,
            'sha256'                => $sha256,
            'status'                => $status,
            'customer_status'       => $customer_status,
            'readable'              => $readable,
            'complete'              => $complete,
            'type_ok'               => $type_ok,
            'expiry_date'           => $expiry,
            'appears_expired'       => $appears_expired,
            'name_match'            => $name_match,
            'required_fields'       => $required_fields,
            'issues'                => array_values(array_unique($issues)),
            'customer_corrections'  => array_values(array_unique($corrections)),
            'human_review_reasons'  => array_values(array_unique($review)),
            'legal_verification'    => false,
            'checked_at'            => gmdate('c'),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @param array<string, mixed>             $context
     * @return array<string, mixed>
     */
    public static function summarize($results, $context = array()) {
        $blocking = array();
        $corrections = array();
        $review_reasons = array();
        $needs_human = false;
        $has_identity = false;
        $identity_ok = false;
        $evidence_count = 0;
        $rejected = 0;
        $accepted = 0;

        foreach ($results as $row) {
            if (!is_array($row)) {
                continue;
            }
            $field = sanitize_key((string) ($row['field'] ?? ''));
            if ($field === 'identity_document') {
                $has_identity = true;
                if (($row['status'] ?? '') !== self::STATUS_FAIL) {
                    $identity_ok = true;
                }
            } elseif (strpos($field, 'evidence_') === 0) {
                $evidence_count++;
            }
            if (($row['status'] ?? '') === self::STATUS_FAIL) {
                $rejected++;
                foreach ((array) ($row['customer_corrections'] ?? array()) as $line) {
                    $corrections[] = $line;
                }
                $blocking[] = array(
                    'field'    => $field,
                    'filename' => (string) ($row['filename'] ?? ''),
                    'issues'   => (array) ($row['issues'] ?? array()),
                );
            } elseif (($row['status'] ?? '') === self::STATUS_PASS) {
                $accepted++;
            }
            if (!empty($row['human_review_reasons'])) {
                $needs_human = true;
                foreach ((array) $row['human_review_reasons'] as $reason) {
                    $review_reasons[] = $reason;
                }
            }
            if (($row['status'] ?? '') === self::STATUS_REVIEW) {
                $needs_human = true;
            }
        }

        $missing = array();
        if (!$has_identity) {
            $missing[] = __('An identity document is required.', 'paxdesign-booking');
        } elseif (!$identity_ok) {
            $missing[] = __('Please replace the identity document with a readable, complete file.', 'paxdesign-booking');
        }

        $disclaimer = __('These are automated preliminary quality checks, not legal verification. A PAXDesign administrator reviews identity and evidence before a final decision.', 'paxdesign-booking');

        $next = '';
        if (!empty($blocking)) {
            $next = __('Correct the rejected files and resubmit them on this same case. Your reference number will not change.', 'paxdesign-booking');
        } elseif ($needs_human) {
            $next = __('Your files were received. Some items need administrator review before the case can proceed.', 'paxdesign-booking');
        } else {
            $next = __('Your submission is queued for the Cybercrime Support team.', 'paxdesign-booking');
        }

        return array(
            'version'              => '1',
            'checked_at'           => gmdate('c'),
            'legal_verification'   => false,
            'disclaimer'           => $disclaimer,
            'files'                => $results,
            'needs_human_review'   => $needs_human || !empty($blocking),
            'human_review_reasons' => array_values(array_unique($review_reasons)),
            'blocking'             => $blocking,
            'customer_corrections' => array_values(array_unique($corrections)),
            'missing'              => $missing,
            'rejected_count'       => $rejected,
            'accepted_count'       => $accepted,
            'evidence_count'       => $evidence_count,
            'identity_present'     => $has_identity,
            'identity_accepted'    => $identity_ok,
            'next_action'          => $next,
            'category'             => sanitize_key((string) ($context['category'] ?? '')),
        );
    }

    /**
     * @param array<string, mixed> $summary
     * @return bool
     */
    public static function has_blocking_identity_failure($summary) {
        if (!is_array($summary)) {
            return false;
        }
        foreach ((array) ($summary['blocking'] ?? array()) as $item) {
            if (is_array($item) && sanitize_key((string) ($item['field'] ?? '')) === 'identity_document') {
                return true;
            }
        }
        return empty($summary['identity_accepted']) && !empty($summary['identity_present']);
    }

    /**
     * Customer-facing subset (no hashes of other customers, no server paths).
     *
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    public static function customer_view($summary) {
        if (!is_array($summary)) {
            return array();
        }
        $files = array();
        foreach ((array) ($summary['files'] ?? array()) as $file) {
            if (!is_array($file)) {
                continue;
            }
            $files[] = array(
                'field'                => (string) ($file['field'] ?? ''),
                'filename'             => (string) ($file['filename'] ?? ''),
                'status'               => (string) ($file['status'] ?? ''),
                'customer_status'      => (string) ($file['customer_status'] ?? ''),
                'readable'             => !empty($file['readable']),
                'complete'             => !empty($file['complete']),
                'type_ok'              => !empty($file['type_ok']),
                'appears_expired'      => !empty($file['appears_expired']),
                'expiry_date'          => (string) ($file['expiry_date'] ?? ''),
                'issues'               => array_values((array) ($file['issues'] ?? array())),
                'customer_corrections' => array_values((array) ($file['customer_corrections'] ?? array())),
                'legal_verification'   => false,
            );
        }

        return array(
            'legal_verification'   => false,
            'disclaimer'           => (string) ($summary['disclaimer'] ?? ''),
            'files'                => $files,
            'needs_human_review'   => !empty($summary['needs_human_review']),
            'customer_corrections' => array_values((array) ($summary['customer_corrections'] ?? array())),
            'missing'              => array_values((array) ($summary['missing'] ?? array())),
            'rejected_count'       => (int) ($summary['rejected_count'] ?? 0),
            'accepted_count'       => (int) ($summary['accepted_count'] ?? 0),
            'next_action'          => (string) ($summary['next_action'] ?? ''),
            'checked_at'           => (string) ($summary['checked_at'] ?? ''),
        );
    }

    /**
     * @param string $field
     * @return list<string>
     */
    private static function required_fields_for_field($field) {
        if ($field === 'identity_document') {
            return array('full_name', 'document_type', 'document_number', 'expiry_date', 'photo');
        }
        return array('readable_content');
    }

    /**
     * @param string $mime
     * @param string $ext
     */
    private static function is_image_mime($mime, $ext) {
        if (strpos($mime, 'image/') === 0) {
            return true;
        }
        return in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'), true);
    }

    /**
     * Lightweight brightness/contrast heuristic. Returns '' when GD is unavailable.
     *
     * @param string $path
     * @param int    $width
     * @param int    $height
     * @return string
     */
    private static function estimate_image_readability($path, $width, $height) {
        if (!function_exists('imagecreatefromstring') || $width < 20 || $height < 20) {
            return '';
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        $im = @imagecreatefromstring($raw);
        if (!$im) {
            return '';
        }
        $tw = 48;
        $th = 48;
        $thumb = imagecreatetruecolor($tw, $th);
        if (!$thumb) {
            imagedestroy($im);
            return '';
        }
        imagecopyresampled($thumb, $im, 0, 0, 0, 0, $tw, $th, $width, $height);
        $sum = 0;
        $min = 255;
        $max = 0;
        $count = $tw * $th;
        for ($y = 0; $y < $th; $y++) {
            for ($x = 0; $x < $tw; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $luma = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                $sum += $luma;
                if ($luma < $min) {
                    $min = $luma;
                }
                if ($luma > $max) {
                    $max = $luma;
                }
            }
        }
        imagedestroy($thumb);
        imagedestroy($im);
        $avg = $count > 0 ? ($sum / $count) : 0;
        $range = $max - $min;
        if ($avg < 28) {
            return 'too_dark';
        }
        if ($avg > 245) {
            return 'too_bright';
        }
        if ($range < 18) {
            return 'low_contrast';
        }
        return '';
    }

    /**
     * @param string $path
     * @return string
     */
    private static function read_exif_datetime($path) {
        if (!function_exists('exif_read_data') || !is_readable($path)) {
            return '';
        }
        $exif = @exif_read_data($path, 'IFD0', true);
        if (!is_array($exif)) {
            return '';
        }
        foreach (array('DateTimeOriginal', 'DateTimeDigitized', 'DateTime') as $key) {
            if (!empty($exif['EXIF'][$key]) && is_string($exif['EXIF'][$key])) {
                return sanitize_text_field($exif['EXIF'][$key]);
            }
            if (!empty($exif[$key]) && is_string($exif[$key])) {
                return sanitize_text_field($exif[$key]);
            }
        }
        return '';
    }

    /**
     * Best-effort expiry detection from filename (and not from EXIF capture dates).
     *
     * @param string $filename
     * @param string $path
     * @return array{date?:string,expired?:bool}
     */
    public static function extract_possible_expiry($filename, $path = '') {
        unset($path);
        $filename = (string) $filename;
        if ($filename === '') {
            return array();
        }
        $lower = strtolower($filename);
        if (preg_match('/expir(?:y|ed|es)?|valid(?:ity)?|until|giltig|ablauf/i', $lower) !== 1) {
            return array();
        }
        if (preg_match('/(20\d{2})[-_.\/](\d{1,2})[-_.\/](\d{1,2})/', $filename, $m)) {
            $stamp = strtotime(sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]));
            if ($stamp) {
                $date = gmdate('Y-m-d', $stamp);
                return array(
                    'date'    => $date,
                    'expired' => $stamp < (time() - DAY_IN_SECONDS),
                );
            }
        }
        if (preg_match('/(\d{1,2})[-_.\/](\d{1,2})[-_.\/](20\d{2})/', $filename, $m)) {
            $stamp = strtotime(sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]));
            if ($stamp) {
                return array(
                    'date'    => gmdate('Y-m-d', $stamp),
                    'expired' => $stamp < (time() - DAY_IN_SECONDS),
                );
            }
        }
        if (strpos($lower, 'expired') !== false) {
            return array('date' => '', 'expired' => true);
        }
        return array();
    }

    /**
     * @param string $reporter_name
     * @param string $filename
     * @return string match|mismatch|unknown
     */
    public static function name_matches_filename($reporter_name, $filename) {
        $reporter_name = trim((string) $reporter_name);
        $filename = strtolower((string) $filename);
        if ($reporter_name === '' || $filename === '') {
            return 'unknown';
        }
        $tokens = preg_split('/[\s,._-]+/', $reporter_name) ?: array();
        $hits = 0;
        $considered = 0;
        foreach ($tokens as $token) {
            $token = strtolower(preg_replace('/[^a-zA-Z\x{00C0}-\x{024F}]/u', '', $token) ?: '');
            if (strlen($token) < 3) {
                continue;
            }
            $considered++;
            if (strpos($filename, $token) !== false) {
                $hits++;
            }
        }
        if ($considered === 0) {
            return 'unknown';
        }
        return $hits > 0 ? 'match' : 'mismatch';
    }
}
