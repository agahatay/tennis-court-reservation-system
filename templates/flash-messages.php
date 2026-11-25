<?php foreach (pull_flash_messages() as $flashMessage): ?>
    <div class="alert alert-<?= e($flashMessage['type']) ?>" role="<?= $flashMessage['type'] === 'error' ? 'alert' : 'status' ?>">
        <?= e($flashMessage['message']) ?>
    </div>
<?php endforeach; ?>
