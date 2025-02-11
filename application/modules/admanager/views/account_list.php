
    <table border="1" class="table table-bordered">
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php foreach ($accounts as $account): ?>
        <tr>
            <td><?php echo $account['name']; ?></td>
            <td><?php echo $account['email']; ?></td>
            <td><?php echo $account['enabled'] ? 'Enabled' : 'Disabled'; ?></td>
            <td>
                <?php if ($account['enabled']): ?>
                <a href="<?php echo site_url('admanager/disable_account/' . $account['email']); ?>">Disable</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
