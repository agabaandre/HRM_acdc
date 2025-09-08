<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, rgba(52, 143, 65, 1) 0%, rgba(52, 143, 65, 0.8) 100%); color: white;">
                <h4 class="card-title mb-0">
                    <i class="fas fa-tags me-2"></i>Generate Division Short Names
                </h4>
            </div>
            
            <div class="card-body">
                <?php if (!$has_short_name_column): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error:</strong> Column 'division_short_name' does not exist in the divisions table.
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-primary">
                                        <i class="fas fa-list-ol me-2"></i>Divisions Without Short Names
                                    </h5>
                                    <h2 class="text-primary"><?= $total_divisions ?></h2>
                                    <p class="text-muted mb-0">Total divisions that need short names</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title">
                                        <i class="fas fa-check-circle me-2"></i>Ready to Generate
                                    </h5>
                                    <p class="mb-0">Click the button below to generate short names for all divisions</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($divisions_without_short_names)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Preview:</strong> The following divisions will be updated with short names:
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Division Name</th>
                                        <th>Proposed Short Name</th>
                                        <th>Division ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($divisions_without_short_names as $division): ?>
                                        <?php 
                                        $shortName = $this->settings_mdl->generateShortCodeFromDivision($division->division_name);
                                        if (empty($shortName)) {
                                            $shortName = 'DIV' . $division->division_id;
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($division->division_name) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary fs-6"><?= htmlspecialchars($shortName) ?></span>
                                            </td>
                                            <td>
                                                <code><?= $division->division_id ?></code>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="<?= base_url('settings/force_generate_short_names') ?>" class="btn btn-success btn-lg" onclick="return confirm('Are you sure you want to generate short names for all divisions? This action cannot be undone.')">
                                <i class="fas fa-magic me-2"></i>Generate Short Names Now
                            </a>
                            
                            <a href="<?= base_url('settings/divisions') ?>" class="btn btn-outline-secondary btn-lg ms-3">
                                <i class="fas fa-arrow-left me-2"></i>Back to Divisions
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Great!</strong> All divisions already have short names assigned.
                        </div>
                        
                        <div class="text-center">
                            <a href="<?= base_url('settings/divisions') ?>" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Divisions
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
