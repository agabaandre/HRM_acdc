<?php
/**
 * Shared Page Header Component
 * Usage: $this->load->view('templates/partials/shared_page_header', $header_data);
 * 
 * $header_data should contain:
 * - title: Main page title
 * - subtitle: Optional subtitle/description
 * - icon: FontAwesome icon class (e.g., 'fa-tasks', 'fa-calendar-week')
 * - actions: Array of action buttons (optional)
 *   - text: Button text
 *   - icon: FontAwesome icon class
 *   - class: Button CSS classes
 *   - onclick: JavaScript function or data attributes
 * - bg_color: Custom background color (optional, defaults to theme color)
 */
?>

<style>
  /* Shared Page Header Styling */
  .shared-page-header {
    background: <?= isset($bg_color) ? $bg_color : 'rgba(52, 143, 65, 1)' ?>;
    color: white;
    padding: 1.2rem 0;
    margin-bottom: 1rem;
    border-radius: 0 0 10px 10px;
  }

  .shared-page-header .container-fluid {
    position: relative;
    z-index: 1;
  }

  .header-actions .btn-modern {
    border-radius: 25px;
    padding: 0.5rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
    margin-left: 0.5rem;
  }

  .header-actions .btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  }

  @media (max-width: 768px) {
    .shared-page-header {
      padding: 1rem 0;
    }
    
    .shared-page-header h4 {
      font-size: 1.5rem;
    }
    
    .header-actions {
      margin-top: 1rem;
    }
    
    .header-actions .btn-modern {
      margin: 0.25rem;
      font-size: 0.9rem;
    }
  }
</style>

<!-- Shared Page Header -->
<div class="shared-page-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-md-8">
        <h4 class="mb-0 text-white">
          <i class="fa <?= isset($icon) ? $icon : 'fa-dashboard' ?> me-2 text-white"></i>
          <?= isset($title) ? $title : 'Page Title' ?>
        </h4>
        <?php if (isset($subtitle) && !empty($subtitle)): ?>
          <p class="mb-0 opacity-75"><?= $subtitle ?></p>
        <?php endif; ?>
      </div>
      <?php if (isset($actions) && !empty($actions)): ?>
        <div class="col-md-4 text-end header-actions d-flex justify-content-end">
          <?php foreach ($actions as $action): ?>
            <button class="btn <?= isset($action['class']) ? $action['class'] : 'btn-light' ?> btn-modern" 
                    <?= isset($action['onclick']) ? $action['onclick'] : '' ?>
                    <?= isset($action['data-bs-toggle']) ? 'data-bs-toggle="' . $action['data-bs-toggle'] . '"' : '' ?>
                    <?= isset($action['data-bs-target']) ? 'data-bs-target="' . $action['data-bs-target'] . '"' : '' ?>
                    <?= isset($action['id']) ? 'id="' . $action['id'] . '"' : '' ?>>
              <?php if (isset($action['icon'])): ?>
                <i class="fa <?= $action['icon'] ?> me-1"></i>
              <?php endif; ?>
              <?= $action['text'] ?>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
