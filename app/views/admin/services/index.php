<?php
/**
 * app/views/admin/services/index.php
 *
 * Services list + Alpine-powered create/edit modal.
 *
 * The modal Alpine state is reopened automatically (with prior values) if
 * the previous POST failed validation — that lets us redirect-Post-Get
 * without losing the user's input.
 */

/** @var list<array<string,mixed>> $services */
/** @var list<string>              $presets  */

$reopen      = !empty($_SESSION['_errors'] ?? null);
$oldData     = $_SESSION['_old'] ?? [];
$reopenForm  = [
    'id'               => $oldData['id']               ?? null,
    'name'             => $oldData['name']             ?? '',
    'description'      => $oldData['description']      ?? '',
    'duration_minutes' => (int) ($oldData['duration_minutes'] ?? 60),
    'price'            => (string) ($oldData['price']  ?? '0.00'),
    'color'            => $oldData['color']            ?? '#C84B31',
    'is_active'        => isset($oldData['is_active']) ? (int) $oldData['is_active'] : 1,
];
$errorsJson = htmlspecialchars(
    json_encode($_SESSION['_errors'] ?? new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
    ENT_QUOTES, 'UTF-8',
);
$reopenFormJson = htmlspecialchars(
    json_encode($reopenForm, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
    ENT_QUOTES, 'UTF-8',
);
$presetsJson = htmlspecialchars(
    json_encode($presets, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
    ENT_QUOTES, 'UTF-8',
);
?>
<div
    x-data='{
        open: <?= $reopen ? 'true' : 'false' ?>,
        isEdit: <?= ($reopen && !empty($reopenForm["id"])) ? 'true' : 'false' ?>,
        form: <?= $reopenFormJson ?>,
        errors: <?= $errorsJson ?>,
        presets: <?= $presetsJson ?>,
        defaultForm: { id: null, name: "", description: "", duration_minutes: 60, price: "0.00", color: "<?= e($presets[0] ?? '#C84B31') ?>", is_active: 1 },
        openCreate() {
            this.isEdit = false;
            this.errors = {};
            this.form = JSON.parse(JSON.stringify(this.defaultForm));
            this.open = true;
            this.$nextTick(() => this.$refs.nameInput && this.$refs.nameInput.focus());
        },
        openEdit(svc) {
            this.isEdit = true;
            this.errors = {};
            this.form = { ...svc, is_active: Number(svc.is_active) };
            this.open = true;
            this.$nextTick(() => this.$refs.nameInput && this.$refs.nameInput.focus());
        },
        close() { this.open = false; }
    }'
    @keydown.escape.window="open && close()"
>
    <header class="page-header">
        <div>
            <h1 class="page-title">Services</h1>
            <p class="page-sub">What you offer, how long it takes, what it costs.</p>
        </div>
        <button type="button" class="btn btn-accent" @click="openCreate()">
            <i data-lucide="plus"></i> Add service
        </button>
    </header>

    <?php if ($services === []): ?>
        <section class="empty-state">
            <svg class="empty-illu" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <rect x="12" y="22" width="72" height="56" rx="8" stroke="currentColor" stroke-width="2"/>
                <path d="M12 38 H84" stroke="currentColor" stroke-width="2"/>
                <circle cx="28" cy="30" r="2" fill="currentColor"/>
                <circle cx="36" cy="30" r="2" fill="currentColor"/>
                <circle cx="44" cy="30" r="2" fill="currentColor"/>
                <path d="M28 56 H68" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M28 64 H56" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <h3>No services yet</h3>
            <p>Add your first service to start accepting bookings.</p>
            <button type="button" class="btn btn-accent" @click="openCreate()">Add your first service</button>
        </section>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Colour</th>
                        <th>Active</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($services as $svc): ?>
                    <?php
                    $svcJson = htmlspecialchars(
                        json_encode($svc, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                        ENT_QUOTES, 'UTF-8',
                    );
                    ?>
                    <tr id="service-row-<?= (int) $svc['id'] ?>">
                        <td>
                            <div style="font-weight: 500;"><?= e((string) $svc['name']) ?></div>
                            <?php if (!empty($svc['description'])): ?>
                                <div class="muted" style="font-size: 0.85rem; margin-top: 2px;">
                                    <?= e(mb_strimwidth((string) $svc['description'], 0, 80, '…')) ?>
                                </div>
                            <?php endif ?>
                        </td>
                        <td><?= (int) $svc['duration_minutes'] ?> min</td>
                        <td>&#8369;<?= e(number_format((float) $svc['price'], 2)) ?></td>
                        <td>
                            <span class="color-dot" style="background-color: <?= e((string) $svc['color']) ?>;"
                                  title="<?= e((string) $svc['color']) ?>"></span>
                            <span class="muted" style="font-size: 0.82rem; margin-left: 6px;">
                                <?= e(strtoupper((string) $svc['color'])) ?>
                            </span>
                        </td>
                        <td>
                            <label class="toggle">
                                <input type="checkbox"
                                       <?= $svc['is_active'] ? 'checked' : '' ?>
                                       hx-post="/admin/services/<?= (int) $svc['id'] ?>/toggle"
                                       hx-swap="none">
                                <span class="track"><span class="thumb"></span></span>
                            </label>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline btn-sm"
                                    @click='openEdit(<?= $svcJson ?>)'>Edit</button>
                            <button type="button" class="btn btn-outline btn-sm btn-danger-outline"
                                    hx-post="/admin/services/<?= (int) $svc['id'] ?>/delete"
                                    hx-confirm="Delete this service? This cannot be undone."
                                    hx-target="#service-row-<?= (int) $svc['id'] ?>"
                                    hx-swap="outerHTML">Delete</button>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>

    <!-- ============================================================
         Create / Edit modal
         ============================================================ -->
    <div x-show="open"
         x-cloak
         class="modal-backdrop"
         x-transition.opacity
         @click.self="close()"
         role="dialog"
         aria-modal="true"
         aria-labelledby="service-modal-title">
        <div class="modal-panel"
             x-transition:enter="modal-enter"
             x-transition:enter-start="modal-enter-from"
             x-transition:enter-end="modal-enter-to"
             @click.stop>

            <header class="modal-header">
                <h2 id="service-modal-title" class="modal-title" x-text="isEdit ? 'Edit service' : 'New service'"></h2>
                <button type="button" class="modal-close" @click="close()" aria-label="Close">&times;</button>
            </header>

            <form :action="isEdit ? '/admin/services/' + form.id : '/admin/services'" method="post" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="svc-name">Name</label>
                    <input id="svc-name" name="name" type="text" maxlength="150" required
                           x-model="form.name" x-ref="nameInput"
                           :class="{ 'is-invalid': errors.name }">
                    <p class="field-error" x-show="errors.name" x-text="errors.name"></p>
                </div>

                <div class="field">
                    <label for="svc-desc">Description <span class="muted">(optional)</span></label>
                    <textarea id="svc-desc" name="description" rows="3" x-model="form.description"></textarea>
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label for="svc-duration">Duration (minutes)</label>
                        <input id="svc-duration" name="duration_minutes" type="number" min="5" max="720" required
                               x-model.number="form.duration_minutes"
                               :class="{ 'is-invalid': errors.duration_minutes }">
                        <p class="field-error" x-show="errors.duration_minutes" x-text="errors.duration_minutes"></p>
                    </div>

                    <div class="field">
                        <label for="svc-price">Price (&#8369;)</label>
                        <input id="svc-price" name="price" type="number" min="0" max="99999.99" step="0.01" required
                               x-model="form.price"
                               :class="{ 'is-invalid': errors.price }">
                        <p class="field-error" x-show="errors.price" x-text="errors.price"></p>
                    </div>
                </div>

                <div class="field">
                    <label>Colour</label>
                    <input type="hidden" name="color" :value="form.color">
                    <div class="swatch-grid">
                        <template x-for="c in presets" :key="c">
                            <button type="button"
                                    class="swatch"
                                    :class="{ 'is-selected': form.color === c }"
                                    :style="{ backgroundColor: c }"
                                    :aria-label="'Pick ' + c"
                                    @click="form.color = c"></button>
                        </template>
                    </div>
                    <p class="field-error" x-show="errors.color" x-text="errors.color"></p>
                </div>

                <div class="field" style="margin-bottom: 8px;">
                    <label class="toggle-inline">
                        <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                        <label class="toggle">
                            <input type="checkbox" x-model="form.is_active" :true-value="1" :false-value="0">
                            <span class="track"><span class="thumb"></span></span>
                        </label>
                        <span style="margin-left: 12px;">Active &mdash; visible to customers</span>
                    </label>
                </div>

                <footer class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="close()">Cancel</button>
                    <button type="submit" class="btn btn-accent">
                        <span x-text="isEdit ? 'Save changes' : 'Create service'"></span>
                    </button>
                </footer>
            </form>
        </div>
    </div>
</div>
