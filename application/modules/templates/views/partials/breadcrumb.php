<?php $is_cbp_home = ($this->uri->segment(1) === 'home'); ?>
<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!--breadcrumb-->
        <?php if (!$is_cbp_home) : ?>
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3"><a href='<?php echo base_url()?><?=$this->uri->segment(1); ?>' style="color:#947645;"><?= ucwords($this->uri->segment(1)); ?></a></div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page"><?= ucwords(str_replace('-',' ',$title)) ?></li>
                    </ol>
                </nav>
            </div>

        </div>
        <?php endif; ?>
        <!--end breadcrumb-->
        <div id="preloader">
            <div id="status">
            </div>
        </div>
        <?php if ($is_cbp_home) : ?>
        <div class="cbp-home-shell">
            <div class="cbp-home-shell-inner">
        <?php else : ?>
        <div class="card">
            <div class="card-body">
        <?php endif; ?>