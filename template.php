<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="ntf-fence-widget">

  <div class="ntf-utility-bar">
    <div class="ntf-utility-inner">
      <span>Tampa Bay's Fence Installation Experts</span>
      <span class="ntf-utility-phone">Family-Owned Since 2012 &middot; 10-Year Labor Warranty</span>
    </div>
  </div>

  <div class="ntf-topbar">
    <div class="ntf-topbar-inner">
      <div class="ntf-brand">
        <img class="ntf-brand-logo" src="<?php echo esc_url(plugins_url('assets/logo.png', __FILE__)); ?>" alt="New Tampa Fence, Inc.">
        <div class="ntf-brand-text">
          <div class="ntf-kicker">Free Instant Estimate</div>
          <h2 class="ntf-h1">Privacy Fence Estimator</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="ntf-wrap">

    <!-- ===================== LEAD GATE ===================== -->
    <div class="ntf-view active" data-ntf-view="gate">
      <div class="ntf-gate-card">
        <div class="ntf-eyebrow">Before we show you pricing</div>
        <h2 class="ntf-h2" style="max-width:none;">Let's build your fence estimate.</h2>
        <p class="ntf-p" style="max-width:60ch;">Tell us a little about your project and we'll unlock the calculator with real pricing right away. A member of our team will follow up to schedule your free on-site measurement.</p>

        <form id="ntfGateForm" class="ntf-gate-form" novalidate>
          <div class="ntf-field-grid ntf-gate-grid">
            <div>
              <label>Full name</label>
              <input type="text" id="ntfLeadName" required>
            </div>
            <div>
              <label>Phone</label>
              <input type="tel" id="ntfLeadPhone" required>
            </div>
            <div>
              <label>Email</label>
              <input type="email" id="ntfLeadEmail" required>
            </div>
            <div>
              <label>Fence type you're interested in</label>
              <select id="ntfLeadFenceType" required></select>
            </div>
          </div>
          <div id="ntfGateError" class="ntf-gate-error" style="display:none;"></div>
          <button type="submit" class="ntf-cta-btn ntf-gate-submit" id="ntfGateSubmit">See My Estimate &rarr;</button>
          <div class="ntf-gate-fineprint">By continuing, you agree to be contacted about your project by phone, text, or email.</div>
        </form>
      </div>
    </div>

    <!-- ===================== ESTIMATE VIEW ===================== -->
    <div class="ntf-view" data-ntf-view="estimate">
      <div class="ntf-hero">
        <div class="ntf-eyebrow">Free instant estimate</div>
        <h2 class="ntf-h2">See what your new privacy fence will cost.</h2>
        <p class="ntf-p">Pick your material, height, and length, add any gates, and get a real price range in seconds. It's a starting point &mdash; your final number comes from a free on-site measurement.</p>
      </div>

      <div class="ntf-layout">
        <div>

          <div class="ntf-card">
            <h3 class="ntf-h3"><span class="ntf-step-num">1</span>Choose your fence material</h3>
            <div class="ntf-material-grid" id="ntfMaterialGrid"></div>
          </div>

          <div class="ntf-card">
            <h3 class="ntf-h3"><span class="ntf-step-num">2</span>Choose your fence height</h3>
            <div class="ntf-height-row" id="ntfHeightRow"></div>
          </div>

          <div class="ntf-card">
            <h3 class="ntf-h3"><span class="ntf-step-num">3</span>How many feet of fence do you need?</h3>
            <div class="ntf-len-row">
              <input type="number" id="ntfLenInput" class="ntf-len-input" value="150" min="0" step="1">
              <input type="range" id="ntfLenSlider" min="20" max="600" step="5" value="150">
            </div>
            <div class="ntf-help">Not sure? Add up the sides of your yard you want fenced, in feet. A typical quarter-acre backyard runs 150&ndash;200 ft.</div>
          </div>

          <div class="ntf-card">
            <h3 class="ntf-h3"><span class="ntf-step-num">4</span>Add your gates</h3>
            <div id="ntfGateList"></div>
            <button class="ntf-add-gate" id="ntfAddGate">+ Add a gate</button>
          </div>

          <div class="ntf-card">
            <h3 class="ntf-h3"><span class="ntf-step-num">5</span>Site conditions</h3>
            <div class="ntf-toggle-row">
              <div>
                <div class="ntf-toggle-label">Remove an existing fence first</div>
                <div class="ntf-toggle-sub">Tear-out and haul-away of your old fence</div>
              </div>
              <label class="ntf-switch"><input type="checkbox" id="ntfRemoveOld"><span class="ntf-slider"></span></label>
            </div>
          </div>

          <div class="ntf-card ntf-card-flush">
            <div class="ntf-preview-head"><h3 class="ntf-h3">Preview</h3></div>
            <div class="ntf-preview-box"><svg id="ntfPreviewSvg" viewBox="0 0 900 260" xmlns="http://www.w3.org/2000/svg"></svg></div>
            <div class="ntf-preview-note">Illustrative preview &mdash; not to exact scale.</div>
          </div>

        </div>

        <div class="ntf-sticky">
          <div class="ntf-est-card">
            <h3 class="ntf-est-h3">Your estimate</h3>
            <div class="ntf-est-range" id="ntfEstRange">$0 &ndash; $0</div>
            <div class="ntf-est-sub" id="ntfEstSub">for 150 ft of 6 ft wood privacy fence</div>

            <div class="ntf-line-items" id="ntfLineItems"></div>

            <a class="ntf-cta-btn" id="ntfCtaBtn" href="#">Request a Free On-Site Quote</a>
            <div class="ntf-cta-sub" id="ntfCtaSub">Prices are estimates only. Your firm quote comes after a free measurement.</div>

            <div class="ntf-disclaimer">This calculator gives a planning-level price range based on typical material and labor costs. Actual pricing depends on your yard's terrain, access, permits, and final material selection.</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
