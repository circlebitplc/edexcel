<?php
/**
 * Shared teacher credit for Reveal.js decks under /ppt.
 * Usage: require_once .../ppt/_teacher_credit.php;
 *        echo ppt_teacher_credit_markup();
 */

if (!function_exists('ppt_teacher_credit_data')) {
	function ppt_teacher_credit_data(): array
	{
		static $data = null;
		if ($data !== null) {
			return $data;
		}

		$teacherId = 14;
		$teacherName = 'Enidu Batuwanthudawe';
		$teacherProfileUrl = '/teachers/teacher_profile.php?id=' . $teacherId;
		$teacherPhotoUrl = '/assets/images/teachers/' . $teacherId . '.png';
		$teacherSocial = [
			'instagram' => '',
			'facebook' => '',
			'linkedin' => '',
		];

		$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');

		$helpers = $docRoot . '/includes/helpers.php';
		if ($docRoot !== '' && is_file($helpers)) {
			require_once $helpers;
			if (function_exists('teacherPhotoPath')) {
				$resolved = teacherPhotoPath($teacherId, null);
				if ($resolved) {
					$teacherPhotoUrl = preg_match('#^https?://#i', $resolved)
						? $resolved
						: '/' . ltrim($resolved, '/');
				}
			}
		}

		$dbFile = $docRoot . '/config/database.php';
		if ($docRoot !== '' && is_file($dbFile)) {
			if (!defined('DB_ALLOW_FAILURE')) {
				define('DB_ALLOW_FAILURE', true);
			}
			require_once $dbFile;
			if (isset($pdo) && $pdo instanceof PDO) {
				try {
					$stmt = $pdo->prepare('SELECT facebook, instagram FROM teacher_profiles WHERE teacher_id = ? LIMIT 1');
					$stmt->execute([$teacherId]);
					$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
					$teacherSocial['facebook'] = trim((string)($row['facebook'] ?? ''));
					$teacherSocial['instagram'] = trim((string)($row['instagram'] ?? ''));
				} catch (Throwable $e) {
					// keep empty socials if profile lookup fails
				}
				try {
					$stmt = $pdo->prepare('SELECT linkedin FROM teacher_profiles WHERE teacher_id = ? LIMIT 1');
					$stmt->execute([$teacherId]);
					$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
					$teacherSocial['linkedin'] = trim((string)($row['linkedin'] ?? ''));
				} catch (Throwable $e) {
					// linkedin column may not exist yet
				}
			}
		}

		$hasTeacherSocial = false;
		foreach ($teacherSocial as $url) {
			if ($url !== '') {
				$hasTeacherSocial = true;
				break;
			}
		}

		$data = [
			'id' => $teacherId,
			'name' => $teacherName,
			'profile_url' => $teacherProfileUrl,
			'photo_url' => $teacherPhotoUrl,
			'social' => $teacherSocial,
			'has_social' => $hasTeacherSocial,
			'icons' => [
				'instagram' => 'bi-instagram',
				'facebook' => 'bi-facebook',
				'linkedin' => 'bi-linkedin',
			],
			'labels' => [
				'instagram' => 'Instagram',
				'facebook' => 'Facebook',
				'linkedin' => 'LinkedIn',
			],
		];

		return $data;
	}
}

if (!function_exists('ppt_teacher_credit_markup')) {
	function ppt_teacher_credit_markup(): string
	{
		$t = ppt_teacher_credit_data();
		$e = static function (string $value): string {
			return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		};

		$html = '<p class="myname">'
			. '<a href="' . $e($t['profile_url']) . '" target="_blank" rel="noopener">'
			. '<img src="' . $e($t['photo_url']) . '" alt="' . $e($t['name']) . '" class="teacher-slide-photo" '
			. 'style="width:120px;height:120px;border-radius:50%;object-fit:cover;padding:0;border:3px solid rgba(255,255,255,0.35);display:block;margin:0 auto 12px;">'
			. $e($t['name'])
			. '</a></p>';

		if (!empty($t['has_social'])) {
			$html .= '<p class="myname teacher-slide-socials" style="margin-top:10px;">';
			foreach (['instagram', 'facebook', 'linkedin'] as $network) {
				$url = trim((string)($t['social'][$network] ?? ''));
				if ($url === '') {
					continue;
				}
				$html .= '<a href="' . $e($url) . '" target="_blank" rel="noopener" '
					. 'aria-label="' . $e($t['labels'][$network]) . '" '
					. 'title="' . $e($t['labels'][$network]) . '" '
					. 'style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;margin:0 4px;border-radius:50%;border:1px solid rgba(255,255,255,0.35);color:#fff;text-decoration:none;font-size:0.85em;">'
					. '<i class="bi ' . $e($t['icons'][$network]) . '"></i></a>';
			}
			$html .= '</p>';
		}

		$html .= '<p class="myname"><small>'
			. '. . . . . . . . . . . . . . . . . . . .. . . . . . . . . . . . . . . . . . . . <br>'
			. "<script type='text/javascript'>document.write(moment().format('dddd, MMMM D, YYYY'));</script>"
			. '</small></p>';

		return $html;
	}
}
