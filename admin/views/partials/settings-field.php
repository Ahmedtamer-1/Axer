<?php
/**
 * Renders one settings_schema field. Shared by the plugin settings form
 * and the theme customizer so both render the same field types the same
 * way instead of each hardcoding its own subset.
 *
 * Expects $field (schema field definition) and $value (already-resolved
 * current value) in scope — included via require, so it inherits the
 * caller's local variables rather than taking parameters.
 */

$type = $field['type'] ?? 'text';
$name = (string) ($field['name'] ?? '');
$inputName = $fieldNamePrefix . '[' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ']';
$label = (string) ($field['label'] ?? $name);
$placeholder = (string) ($field['placeholder'] ?? '');
$help = (string) ($field['help'] ?? '');
$required = !empty($field['required']);
?>
<div class="form-group">
    <label class="form-label">
        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><?= $required ? ' <span style="color: var(--danger, #ef4444);">*</span>' : '' ?>
    </label>

    <?php if ($type === 'password'): ?>
        <input type="password" class="form-control" name="<?= $inputName ?>" value=""
               placeholder="<?= $value !== '' && $value !== null ? '•••••••• (leave blank to keep current value)' : htmlspecialchars($placeholder ?: '••••••••', ENT_QUOTES, 'UTF-8') ?>">

    <?php elseif ($type === 'textarea'): ?>
        <textarea class="form-control" rows="4" name="<?= $inputName ?>"
                  placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></textarea>

    <?php elseif ($type === 'select'): ?>
        <select class="form-control" name="<?= $inputName ?>">
            <?php foreach (($field['options'] ?? []) as $option): ?>
                <option value="<?= htmlspecialchars((string) $option, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $value === (string) $option ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $option, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php elseif ($type === 'checkbox'): ?>
        <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 400;">
            <input type="checkbox" name="<?= $inputName ?>" value="1" <?= $value ? 'checked' : '' ?>>
            <span style="color: var(--text-muted);"><?= htmlspecialchars($help ?: 'Enabled', ENT_QUOTES, 'UTF-8') ?></span>
        </label>

    <?php elseif ($type === 'color'): ?>
        <input type="color" name="<?= $inputName ?>" value="<?= htmlspecialchars((string) ($value ?: '#000000'), ENT_QUOTES, 'UTF-8') ?>"
               style="width: 4rem; height: 2.5rem; padding: 0.25rem; border: 1px solid var(--border-color); border-radius: 0.5rem;">

    <?php elseif ($type === 'number'): ?>
        <input type="number" class="form-control" name="<?= $inputName ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>">

    <?php elseif ($type === 'url'): ?>
        <input type="url" class="form-control" name="<?= $inputName ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>">

    <?php else: ?>
        <input type="text" class="form-control" name="<?= $inputName ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <?php if ($help !== '' && $type !== 'checkbox'): ?>
        <p style="margin: 0.375rem 0 0; font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($help, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</div>
