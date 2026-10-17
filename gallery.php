<?php
declare(strict_types=1);
/**
 * The authenticated gallery markup. Included by index.php when already logged in,
 * or fetched as a fragment (gallery.php?fragment=1) right after login.
 */
if (!defined('WABYU_EMBEDDED')) {
    require_once __DIR__ . '/includes/api.php';
    wabyu_install_error_handlers(false);
    wabyu_security_headers();
    wabyu_session_start();
    if (!wabyu_secrets() || !wabyu_is_authenticated()) { http_response_code(401); exit; }
    wabyu_init_storage();
    session_write_close();
    header('Cache-Control: no-store');
}
$maxMb = (int)(WABYU_MAX_IMAGE_BYTES / 1048576);
?>
<div class="gallery-shell" id="gallery-shell" data-max-bytes="<?= WABYU_MAX_IMAGE_BYTES ?>">
  <header class="topbar">
    <a class="wordmark" href="#gallery-title" aria-label="WABYU">WABYU</a>
    <div class="topbar-actions">
      <button class="btn btn-primary" type="button" id="add-memory">Add memory</button>
      <div class="menu">
        <button class="btn btn-icon" type="button" id="account-btn" aria-haspopup="true" aria-expanded="false" aria-controls="account-menu" aria-label="Account menu">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c.9-3.6 3.9-5.4 7.5-5.4s6.6 1.8 7.5 5.4"/></svg>
        </button>
        <div class="menu-panel" id="account-menu" role="menu" hidden>
          <a role="menuitem" class="menu-item" href="api/export.php" download>Download backup</a>
          <button role="menuitem" class="menu-item" type="button" id="logout-btn">Log out</button>
        </div>
      </div>
    </div>
  </header>

  <main class="gallery" id="gallery" tabindex="-1">
    <section class="hero">
      <h1 class="hero-title" id="gallery-title"><?= wabyu_h(WABYU_HERO_TITLE) ?></h1>
      <p class="hero-sub"><?= wabyu_h(WABYU_HERO_SUB) ?></p>
    </section>

    <section class="toolbar" aria-label="Browse memories">
      <div class="tabs" role="group" aria-label="Collections">
        <button type="button" class="tab" data-view="all" aria-pressed="true">All memories</button>
        <button type="button" class="tab" data-view="favorites" aria-pressed="false">Favorites</button>
        <button type="button" class="tab" data-view="recent" aria-pressed="false">Recently added</button>
      </div>
      <div class="filters">
        <label class="sr-only" for="search">Search memories</label>
        <input class="field field-search" id="search" type="search" placeholder="Search titles and words" autocomplete="off" maxlength="80">
        <label class="sr-only" for="category-filter">Category</label>
        <select class="field field-select" id="category-filter" hidden><option value="">All categories</option></select>
      </div>
    </section>

    <p class="status" id="status" role="status" aria-live="polite"></p>
    <div class="cards" id="cards" role="list" aria-busy="true"></div>

    <div class="empty" id="empty-state" hidden>
      <h2 class="empty-title">Nothing here yet</h2>
      <p class="empty-text">Add a photograph and a few words, and it will be kept here.</p>
      <button class="btn btn-primary" type="button" id="empty-add">Add your first memory</button>
    </div>
    <div class="empty" id="no-results" hidden>
      <h2 class="empty-title">No memories match</h2>
      <p class="empty-text">Try a different word, or clear the search and filters.</p>
      <button class="btn" type="button" id="clear-filters">Clear filters</button>
    </div>
  </main>

  <!-- Add / edit memory -->
  <dialog class="dialog dialog-editor" id="editor" aria-labelledby="editor-title">
    <form id="editor-form" novalidate>
      <header class="dialog-head">
        <h2 class="dialog-title" id="editor-title">Add a memory</h2>
        <button class="btn btn-icon" type="button" data-editor-close aria-label="Close">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </header>
      <div class="editor-grid">
        <div class="editor-photo">
          <label class="dropzone" for="image-input" id="dropzone">
            <img id="image-preview" alt="Preview of the selected photograph" hidden>
            <span class="dropzone-text" id="dropzone-text">Choose a photograph<small>JPEG, PNG or WebP, up to <?= $maxMb ?> MB</small></span>
          </label>
          <input class="sr-only" id="image-input" name="image" type="file" accept="image/jpeg,image/png,image/webp">
          <p class="field-error" id="err-image" role="alert"></p>
        </div>
        <div class="editor-fields">
          <div class="form-row">
            <label for="f-title">Title</label>
            <input class="field" id="f-title" name="title" type="text" maxlength="120" required autocomplete="off">
            <p class="field-error" id="err-title" role="alert"></p>
          </div>
          <div class="form-row">
            <label for="f-comment">Dedication</label>
            <textarea class="field" id="f-comment" name="comment" rows="5" maxlength="2000"></textarea>
            <p class="field-hint"><span id="comment-count">0</span> / 2000</p>
          </div>
          <div class="form-split">
            <div class="form-row">
              <label for="f-date">Date</label>
              <input class="field" id="f-date" name="memory_date" type="date">
              <p class="field-error" id="err-memory_date" role="alert"></p>
            </div>
            <div class="form-row">
              <label for="f-category">Category</label>
              <input class="field" id="f-category" name="category" type="text" maxlength="40" list="category-list" autocomplete="off">
              <datalist id="category-list"></datalist>
            </div>
          </div>
          <label class="check"><input type="checkbox" id="f-favorite" name="favorite" value="1"><span>Mark as a favorite</span></label>
        </div>
      </div>
      <p class="form-error" id="editor-error" role="alert"></p>
      <div class="discard-bar" id="discard-bar" hidden>
        <span>Discard your unsaved changes?</span>
        <button class="btn" type="button" id="keep-editing">Keep editing</button>
        <button class="btn btn-quiet" type="button" id="discard-changes">Discard</button>
      </div>
      <footer class="dialog-foot">
        <button class="btn" type="button" data-editor-close>Cancel</button>
        <button class="btn btn-primary" type="submit" id="editor-save">Save memory</button>
      </footer>
    </form>
  </dialog>

  <!-- Full-size viewer -->
  <dialog class="dialog dialog-viewer" id="viewer" aria-labelledby="viewer-title">
    <div class="viewer-stage" id="viewer-stage">
      <img id="viewer-img" alt="">
    </div>
    <aside class="viewer-info">
      <p class="viewer-meta" id="viewer-meta"></p>
      <h2 class="viewer-title" id="viewer-title"></h2>
      <p class="viewer-comment" id="viewer-comment"></p>
      <div class="viewer-actions">
        <button class="btn" type="button" id="viewer-edit">Edit</button>
        <button class="btn btn-quiet" type="button" id="viewer-delete">Delete</button>
      </div>
    </aside>
    <button class="btn btn-icon viewer-close" type="button" id="viewer-close" aria-label="Close viewer"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    <button class="btn btn-icon viewer-nav viewer-prev" type="button" id="viewer-prev" aria-label="Previous photograph"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
    <button class="btn btn-icon viewer-nav viewer-next" type="button" id="viewer-next" aria-label="Next photograph"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
  </dialog>

  <!-- Mandatory deletion confirmation -->
  <dialog class="dialog dialog-confirm" id="delete-dialog" aria-labelledby="delete-title" aria-describedby="delete-text">
    <form id="delete-form" novalidate>
      <h2 class="dialog-title" id="delete-title">Delete this memory?</h2>
      <p class="confirm-text" id="delete-text">This will permanently remove the selected photograph and its dedication.</p>
      <div class="confirm-target"><img id="delete-thumb" alt="" width="44" height="44"><span id="delete-name"></span></div>
      <div class="form-row">
        <label for="delete-password">Confirmation password</label>
        <div class="password-wrap">
          <input class="field" id="delete-password" type="password" placeholder="Enter confirmation password" autocomplete="off" autocapitalize="off" spellcheck="false" aria-describedby="delete-error">
          <button class="btn btn-icon toggle-vis" type="button" id="delete-toggle" aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
        <p class="field-error" id="delete-error" role="alert"></p>
      </div>
      <footer class="dialog-foot">
        <button class="btn" type="button" id="delete-cancel">Cancel</button>
        <button class="btn btn-primary" type="submit" id="delete-confirm">Confirm deletion</button>
      </footer>
    </form>
  </dialog>

  <div class="toasts" id="toasts" role="status" aria-live="polite"></div>
</div>
