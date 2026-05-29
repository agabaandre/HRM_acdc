<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CBP Modules nav dropdown (data from cbp_modules table).
 *
 * Expects: $cbp_nav_home (array label, href, is_active, …), $cbp_nav_modules (API-shaped list)
 */
$cbp_nav_home = isset($cbp_nav_home) && is_array($cbp_nav_home) ? $cbp_nav_home : [
	'label' => 'CBP Home',
	'href' => site_url('home/index'),
	'is_active' => false,
];
$cbp_nav_home_url = isset($cbp_nav_home['href']) ? (string) $cbp_nav_home['href'] : site_url('home/index');
$cbp_nav_home_label = isset($cbp_nav_home['label']) ? (string) $cbp_nav_home['label'] : 'CBP Home';
$cbp_nav_home_active = !empty($cbp_nav_home['is_active']);
$cbp_nav_modules = isset($cbp_nav_modules) && is_array($cbp_nav_modules) ? $cbp_nav_modules : [];
$cbp_toggle_active = $cbp_nav_home_active;
if (!$cbp_toggle_active) {
	foreach ($cbp_nav_modules as $_m) {
		if (!empty($_m['is_active'])) {
			$cbp_toggle_active = true;
			break;
		}
	}
}
unset($_m);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/cbp-modules-nav.css') ?>">

<li class="nav-item cbp-modules-dd" id="cbp-modules-dd">
	<button
		type="button"
		class="cbp-modules-dd-toggle nav-link border-0<?= $cbp_toggle_active ? ' is-active' : '' ?>"
		id="cbp-modules-dd-btn"
		aria-haspopup="true"
		aria-expanded="false"
		aria-controls="cbp-modules-dd-panel"
		title="CBP Modules"
	>
		<i class="bx bx-category" style="color: #fff; font-size: 1.1rem;" aria-hidden="true"></i>
		<span class="cbp-modules-dd-label ms-2 d-none d-md-inline" style="color: #fff; font-size: 0.875rem;">CBP Modules</span>
		<span class="cbp-modules-dd-caret d-none d-md-inline" aria-hidden="true">▼</span>
	</button>
	<div class="cbp-modules-dd-panel" id="cbp-modules-dd-panel" role="menu" aria-labelledby="cbp-modules-dd-btn">
		<a
			href="<?= htmlspecialchars($cbp_nav_home_url, ENT_QUOTES, 'UTF-8') ?>"
			class="cbp-modules-dd-primary<?= $cbp_nav_home_active ? ' is-active' : '' ?>"
			role="menuitem"
		>
			<span class="cbp-modules-dd-primary-title"><?= htmlspecialchars($cbp_nav_home_label, ENT_QUOTES, 'UTF-8') ?></span>
		</a>
		<?php if (count($cbp_nav_modules) > 0) : ?>
			<p class="cbp-modules-dd-section">Systems</p>
			<?php foreach ($cbp_nav_modules as $mod) :
				$href = isset($mod['href']) ? (string) $mod['href'] : '#';
				$label = isset($mod['label']) ? (string) $mod['label'] : 'Module';
				$icon = isset($mod['icon']) ? trim((string) $mod['icon']) : 'fa-th';
				if ($icon === '') {
					$icon = 'fa-th';
				}
				if (strpos($icon, 'fa ') !== 0 && strpos($icon, 'fa-') === 0) {
					$icon = 'fa ' . $icon;
				}
				$absolute = !empty($mod['opens_in_new_tab']) || !empty($mod['absolute']);
				$active = !empty($mod['is_active']);
				$target = $absolute ? ' target="_blank" rel="noopener noreferrer"' : '';
				?>
			<a
				href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
				class="cbp-modules-dd-item<?= $active ? ' is-active' : '' ?>"
				role="menuitem"
				<?= $target ?>
			>
				<i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> cbp-modules-dd-icon" aria-hidden="true"></i>
				<span class="cbp-modules-dd-item-text">
					<span class="cbp-modules-dd-item-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
				</span>
			</a>
			<?php endforeach; ?>
		<?php else : ?>
			<p class="cbp-modules-dd-empty" role="status">No other CBP systems are assigned to your account.</p>
		<?php endif; ?>
	</div>
</li>
<script>
(function () {
	var root = document.getElementById('cbp-modules-dd');
	var btn = document.getElementById('cbp-modules-dd-btn');
	var panel = document.getElementById('cbp-modules-dd-panel');
	if (!root || !btn || !panel) {
		return;
	}
	function closeDd() {
		root.classList.remove('is-open');
		btn.setAttribute('aria-expanded', 'false');
	}
	function toggleDd(e) {
		e.preventDefault();
		e.stopPropagation();
		var open = root.classList.toggle('is-open');
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
	}
	btn.addEventListener('click', toggleDd);
	document.addEventListener('click', function (e) {
		if (!root.contains(e.target)) {
			closeDd();
		}
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			closeDd();
		}
	});
})();
</script>
