(function(){
  "use strict";

  var CFG = window.NTF_FENCE_CONFIG || {};
  var DEFAULT_RATES = CFG.defaults;
  var HEIGHTS = ["4","5","6","8"];
  var MATERIAL_ORDER = ["wood","vinylPrivacy","vinylPicket","aluminum","chainlink","metalDura"];
  var RATES = JSON.parse(JSON.stringify(DEFAULT_RATES));
  var leadUnlocked = false;
  var leadInfo = { name: "", email: "", phone: "" };
  var state = {
    material: "wood",
    height: "6",
    gates: [{ type: "single", qty: 1 }]
  };

  var root = document.querySelector(".ntf-fence-widget");
  if (!root) return;
  var $ = function(sel){ return root.querySelector(sel); };
  var $all = function(sel){ return root.querySelectorAll(sel); };

  function materialKeys(){
    var keys = Object.keys(RATES.fence);
    return MATERIAL_ORDER.filter(function(k){ return keys.indexOf(k) !== -1; })
      .concat(keys.filter(function(k){ return MATERIAL_ORDER.indexOf(k) === -1; }));
  }

  /* A material may not be offered at every height (e.g. Metal Dura Fence has no 8 ft). */
  function priceFor(materialKey, height){
    var m = RATES.fence[materialKey];
    return (m && m.heights && m.heights[height]) ? m.heights[height] : null;
  }
  function availableHeights(materialKey){
    var m = RATES.fence[materialKey];
    if (!m || !m.heights) return [];
    return HEIGHTS.filter(function(h){ return !!m.heights[h]; });
  }
  /* A representative "from" price for the material card — prefer 6 ft, else the first height offered. */
  function referencePrice(materialKey){
    return priceFor(materialKey, "6") || priceFor(materialKey, availableHeights(materialKey)[0]);
  }

  /* ---------------- REST load / lead submit ---------------- */
  function loadRates(){
    return fetch(CFG.restUrl, { credentials: "same-origin" })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(data){
        if (data) {
          RATES = Object.assign(JSON.parse(JSON.stringify(DEFAULT_RATES)), data);
        }
      })
      .catch(function(){ /* fall back to defaults silently */ });
  }

  function submitLead(payload){
    return fetch(CFG.leadsUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    }).then(function(r){ return r.json().then(function(body){ return { ok: r.ok, body: body }; }); });
  }

  /* ---------------- lead gate ---------------- */
  function buildFenceTypeOptions(){
    var sel = $("#ntfLeadFenceType");
    sel.innerHTML = "";
    materialKeys().forEach(function(key){
      var opt = document.createElement("option");
      opt.value = key;
      opt.textContent = RATES.fence[key].label;
      sel.appendChild(opt);
    });
    var other = document.createElement("option");
    other.value = "notsure";
    other.textContent = "Not sure yet";
    sel.appendChild(other);
  }

  function showGateError(msg){
    var el = $("#ntfGateError");
    el.textContent = msg;
    el.style.display = "block";
  }
  function hideGateError(){
    $("#ntfGateError").style.display = "none";
  }

  $("#ntfGateForm").addEventListener("submit", function(e){
    e.preventDefault();
    hideGateError();

    var name = $("#ntfLeadName").value.trim();
    var phone = $("#ntfLeadPhone").value.trim();
    var email = $("#ntfLeadEmail").value.trim();
    var fenceType = $("#ntfLeadFenceType").value;
    var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

    if (!name || !phone || !emailOk) {
      showGateError("Please enter your name, a valid email, and phone number to continue.");
      return;
    }

    var btn = $("#ntfGateSubmit");
    btn.disabled = true;
    btn.textContent = "Loading your estimate...";

    var fenceLabel = (RATES.fence[fenceType] && RATES.fence[fenceType].label) || "Not sure yet";

    submitLead({ stage: "lead", name: name, phone: phone, email: email, fenceType: fenceLabel })
      .then(function(res){
        if (!res.ok) {
          showGateError((res.body && res.body.message) || "Something went wrong. Please try again.");
          btn.disabled = false;
          btn.textContent = "See My Estimate \u2192";
          return;
        }
        leadUnlocked = true;
        leadInfo = { name: name, email: email, phone: phone };
        try { sessionStorage.setItem("ntfLeadSubmitted", "1"); } catch(e){}

        // Carry the chosen material into the calculator, and always start at 6 ft.
        if (RATES.fence[fenceType]) { state.material = fenceType; }
        state.height = "6";

        buildMaterialGrid();
        buildHeightRow();
        recalc();
        drawPreview();
        switchView("estimate");
      })
      .catch(function(){
        showGateError("Something went wrong. Please check your connection and try again.");
        btn.disabled = false;
        btn.textContent = "See My Estimate \u2192";
      });
  });

  try {
    if (sessionStorage.getItem("ntfLeadSubmitted") === "1") { leadUnlocked = true; }
  } catch(e){}

  /* ---------------- view switching (gate -> estimate only; no on-page admin) ---------------- */
  function switchView(name){
    $all("[data-ntf-view]").forEach(function(v){
      v.classList.toggle("active", v.getAttribute("data-ntf-view") === name);
    });
  }

  /* ---------------- material grid ---------------- */
  function buildMaterialGrid(){
    var grid = $("#ntfMaterialGrid");
    grid.innerHTML = "";
    materialKeys().forEach(function(key){
      var m = RATES.fence[key];
      var ref = referencePrice(key);
      var div = document.createElement("div");
      div.className = "ntf-mat-card" + (state.material===key ? " ntf-sel" : "");
      div.addEventListener("click", function(){
        state.material = key;
        // If the height currently selected isn't offered for this material, fall back to 6 ft (or the closest available).
        if (!priceFor(key, state.height)) {
          state.height = availableHeights(key).indexOf("6") !== -1 ? "6" : (availableHeights(key)[0] || "6");
        }
        buildMaterialGrid(); buildHeightRow(); recalc(); drawPreview();
      });
      div.innerHTML = '<div class="ntf-swatch" style="background:'+m.swatch+'"></div>' +
        '<div class="ntf-mname">'+m.label+'</div>' +
        '<div class="ntf-msub">'+(ref ? ('$'+ref.low+'&ndash;$'+ref.high+'/ft') : 'Call for pricing')+'</div>';
      grid.appendChild(div);
    });
  }

  /* ---------------- height row ---------------- */
  function buildHeightRow(){
    var row = $("#ntfHeightRow");
    row.innerHTML = "";
    var offered = availableHeights(state.material);
    HEIGHTS.forEach(function(h){
      var isOffered = offered.indexOf(h) !== -1;
      var pill = document.createElement("div");
      pill.className = "ntf-h-pill" + (state.height===h ? " ntf-sel" : "") + (isOffered ? "" : " ntf-h-pill-disabled");
      pill.textContent = h + " ft" + (isOffered ? "" : " (n/a)");
      if (isOffered) {
        pill.addEventListener("click", function(){
          state.height = h;
          buildHeightRow(); recalc(); drawPreview();
        });
      }
      row.appendChild(pill);
    });
  }

  /* ---------------- gates ---------------- */
  $("#ntfAddGate").addEventListener("click", function(){
    state.gates.push({ type: "single", qty: 1 });
    renderGates(); recalc(); drawPreview();
  });

  function renderGates(){
    var list = $("#ntfGateList");
    list.innerHTML = "";
    if (state.gates.length === 0) {
      list.innerHTML = '<div class="ntf-help" style="margin-top:0;">No gates added. Most yards need at least one walk gate.</div>';
      return;
    }
    state.gates.forEach(function(g, i){
      var row = document.createElement("div");
      row.className = "ntf-gate-row";

      var select = document.createElement("select");
      select.className = "ntf-gate-type";
      select.setAttribute("aria-label", "Gate type");
      Object.keys(RATES.gates).forEach(function(key){
        var opt = document.createElement("option");
        opt.value = key;
        opt.textContent = RATES.gates[key].label;
        if (g.type === key) opt.selected = true;
        select.appendChild(opt);
      });
      select.addEventListener("change", function(){ state.gates[i].type = select.value; recalc(); drawPreview(); });

      /* quantity stepper: [-] [n] [+] */
      var stepper = document.createElement("div");
      stepper.className = "ntf-qty";
      var minus = document.createElement("button");
      minus.type = "button"; minus.className = "ntf-qty-btn"; minus.textContent = "−"; minus.setAttribute("aria-label", "One fewer gate");
      var qty = document.createElement("input");
      qty.type = "number"; qty.className = "ntf-qty-input"; qty.min = "1"; qty.max = "20"; qty.value = g.qty; qty.setAttribute("aria-label", "Number of gates");
      var plus = document.createElement("button");
      plus.type = "button"; plus.className = "ntf-qty-btn"; plus.textContent = "+"; plus.setAttribute("aria-label", "One more gate");
      var setQty = function(n){
        n = Math.min(20, Math.max(1, parseInt(n, 10) || 1));
        state.gates[i].qty = n; qty.value = n;
        minus.disabled = n <= 1;
        recalc(); drawPreview();
      };
      qty.addEventListener("change", function(){ setQty(qty.value); });
      minus.addEventListener("click", function(){ setQty(state.gates[i].qty - 1); });
      plus.addEventListener("click", function(){ setQty(state.gates[i].qty + 1); });
      minus.disabled = g.qty <= 1;
      stepper.appendChild(minus); stepper.appendChild(qty); stepper.appendChild(plus);

      var rm = document.createElement("button");
      rm.type = "button"; rm.className = "ntf-rm-btn"; rm.innerHTML = "&times;<span>Remove</span>"; rm.title = "Remove this gate"; rm.setAttribute("aria-label", "Remove this gate");
      rm.addEventListener("click", function(){
        state.gates.splice(i, 1);
        renderGates(); recalc(); drawPreview();
      });

      row.appendChild(select); row.appendChild(stepper); row.appendChild(rm);
      list.appendChild(row);
    });
  }

  var lastEstimate = { low: 0, high: 0, feet: 0, height: "6", materialLabel: "", gatesSummary: "" };

  /* ---------------- calculation ---------------- */
  function recalc(){
    var feet = Math.max(0, parseFloat($("#ntfLenInput").value) || 0);
    var mat = RATES.fence[state.material];
    var priceRow = priceFor(state.material, state.height);

    // Safety net: the height picker prevents choosing an unavailable height, but guard anyway.
    if (!priceRow) {
      var fallbackHeight = availableHeights(state.material)[0];
      if (fallbackHeight) { state.height = fallbackHeight; priceRow = priceFor(state.material, fallbackHeight); }
    }

    var fLow = priceRow ? feet * priceRow.low : 0;
    var fHigh = priceRow ? feet * priceRow.high : 0;

    var gLow = 0, gHigh = 0;
    var matGateAdj = (RATES.gateMaterialAdjust[state.material] || 0) / 100;
    state.gates.forEach(function(g){
      var base = RATES.gates[g.type];
      gLow += g.qty * base.low * (1 + matGateAdj);
      gHigh += g.qty * base.high * (1 + matGateAdj);
    });

    var rLow = 0, rHigh = 0;
    if ($("#ntfRemoveOld").checked) {
      rLow = feet * RATES.modifiers.removalOldFence.low;
      rHigh = feet * RATES.modifiers.removalOldFence.high;
    }

    var totalLow = fLow + gLow + rLow;
    var totalHigh = fHigh + gHigh + rHigh;

    var round = function(n){ return Math.round(n / 10) * 10; };

    lastEstimate = {
      low: round(totalLow),
      high: round(totalHigh),
      feet: feet,
      height: state.height,
      materialLabel: mat.label,
      gatesSummary: state.gates.map(function(g){ return g.qty + "x " + RATES.gates[g.type].label; }).join(", ") || "None"
    };

    $("#ntfEstRange").textContent = priceRow
      ? ("$" + round(totalLow).toLocaleString() + " – $" + round(totalHigh).toLocaleString())
      : "Call for pricing";
    $("#ntfEstSub").textContent = "for " + feet + " ft of " + state.height + " ft " + mat.label.toLowerCase() + " fence";

    var li = $("#ntfLineItems");
    li.innerHTML = "";
    var addLine = function(label, low, high){
      if (low === 0 && high === 0) return;
      var row = document.createElement("div");
      row.className = "ntf-li-row";
      row.innerHTML = "<span>" + label + "</span><span class=\"ntf-amt\">$" + round(low).toLocaleString() + "–$" + round(high).toLocaleString() + "</span>";
      li.appendChild(row);
    };
    addLine("Fence material &amp; install", fLow, fHigh);
    if (gLow > 0 || gHigh > 0) addLine("Gates (" + state.gates.reduce(function(a,g){ return a+g.qty; }, 0) + ")", gLow, gHigh);
    addLine("Old fence removal", rLow, rHigh);

    var totalRow = document.createElement("div");
    totalRow.className = "ntf-li-total";
    totalRow.innerHTML = "<span>Total estimate</span><span>" + (priceRow ? ("$" + round(totalLow).toLocaleString() + "–$" + round(totalHigh).toLocaleString()) : "Call for pricing") + "</span>";
    li.appendChild(totalRow);

    var phone = RATES.business.phone || "";
    var btn = $("#ntfCtaBtn");
    btn.href = phone ? "tel:" + phone.replace(/[^0-9+]/g, "") : "#";
    btn.textContent = phone ? "Call " + phone + " for a Free On-Site Quote" : "Request a Free On-Site Quote";
  }

  $("#ntfLenInput").addEventListener("input", function(e){
    $("#ntfLenSlider").value = e.target.value;
    recalc(); drawPreview();
  });
  $("#ntfLenSlider").addEventListener("input", function(e){
    $("#ntfLenInput").value = e.target.value;
    recalc(); drawPreview();
  });
  $("#ntfRemoveOld").addEventListener("change", recalc);

  $("#ntfCtaBtn").addEventListener("click", function(){
    if (!leadUnlocked) return;
    submitLead({
      stage: "quote",
      name: leadInfo.name,
      email: leadInfo.email,
      phone: leadInfo.phone,
      fenceType: lastEstimate.materialLabel,
      height: lastEstimate.height,
      feet: lastEstimate.feet,
      gatesSummary: lastEstimate.gatesSummary,
      estimateLow: lastEstimate.low,
      estimateHigh: lastEstimate.high
    }).catch(function(){ /* non-blocking — the call/tel link still proceeds either way */ });
  });

  /* ---------------- preview drawing ---------------- */
  function drawPreview(){
    var svg = $("#ntfPreviewSvg");
    var W = 900, H = 260, groundY = 175;
    var heightPx = { "4": 70, "5": 90, "6": 110, "8": 150 }[state.height];
    var topY = groundY - heightPx;
    var mat = state.material;
    var panels = "";
    var postW = 8, spacing = 62;
    var numPosts = Math.floor(W / spacing);

    var colorMap = {
      wood:         { plank: "#A9713F", plankAlt: "#96622F", post: "#6B4426", cap: "#5A3A21" },
      vinylPrivacy: { plank: "#F4F1E7", plankAlt: "#E9E4D2", post: "#E4DFCE", cap: "#D8D2BC" },
      vinylPicket:  { plank: "#FAF8F2", plankAlt: "#F0ECDF", post: "#E4DFCE", cap: "#D8D2BC" },
      aluminum:     { plank: "#3B4046", plankAlt: "#31363B", post: "#24282C", cap: "#1B1E21" },
      chainlink:    { plank: "#B9C0C4", plankAlt: "#A7AEB2", post: "#5B6367", cap: "#4A5155" },
      metalDura:    { plank: "#2B2F33", plankAlt: "#22262A", post: "#1A1D20", cap: "#111315" }
    };
    var colors = colorMap[mat] || colorMap.wood;

    var isPicketLike = (mat === "vinylPicket");

    for (var i = 0; i < numPosts; i++) {
      var x = i * spacing;
      if (mat === "chainlink") {
        panels += '<rect x="'+x+'" y="'+topY+'" width="'+spacing+'" height="'+heightPx+'" fill="url(#ntfMesh)"/>';
      } else if (isPicketLike) {
        var pw = (spacing - postW - 4) / 3;
        for (var p = 0; p < 3; p++) {
          panels += '<rect x="'+(x+postW+p*pw+1)+'" y="'+(topY-4)+'" width="'+(pw-2)+'" height="'+(heightPx-2)+'" fill="'+(p%2===0?colors.plank:colors.plankAlt)+'" rx="2"/>';
          panels += '<polygon points="'+(x+postW+p*pw+1)+','+(topY-4)+' '+(x+postW+p*pw+1+(pw-2))+','+(topY-4)+' '+(x+postW+p*pw+1+(pw-2)/2)+','+(topY-14)+'" fill="'+colors.plank+'"/>';
        }
      } else {
        panels += '<rect x="'+(x+postW)+'" y="'+(topY+6)+'" width="'+(spacing-postW-4)+'" height="'+(heightPx-6)+'" fill="'+(i%2===0?colors.plank:colors.plankAlt)+'" rx="2"/>';
      }
      panels += '<rect x="'+x+'" y="'+(topY-10)+'" width="'+postW+'" height="'+(heightPx+10)+'" fill="'+colors.post+'"/>';
      panels += '<rect x="'+(x-2)+'" y="'+(topY-16)+'" width="'+(postW+4)+'" height="8" fill="'+colors.cap+'"/>';
    }

    var numGates = state.gates.reduce(function(a,g){ return a+g.qty; }, 0);
    var gateMark = "";
    if (numGates > 0) {
      var gx = W - 140, gw = 70;
      gateMark =
        '<line x1="'+gx+'" y1="'+topY+'" x2="'+gx+'" y2="'+groundY+'" stroke="'+colors.post+'" stroke-width="4"/>' +
        '<line x1="'+gx+'" y1="'+(topY+8)+'" x2="'+(gx+gw)+'" y2="'+(topY+18)+'" stroke="'+colors.post+'" stroke-width="4"/>' +
        '<line x1="'+gx+'" y1="'+(groundY-6)+'" x2="'+(gx+gw)+'" y2="'+(groundY-16)+'" stroke="'+colors.post+'" stroke-width="4"/>' +
        '<circle cx="'+(gx+gw)+'" cy="'+((topY+groundY)/2)+'" r="4" fill="'+colors.cap+'"/>';
    }

    svg.innerHTML =
      '<defs><pattern id="ntfMesh" width="16" height="16" patternUnits="userSpaceOnUse">' +
      '<path d="M0 8 L8 0 L16 8 L8 16 Z" fill="none" stroke="'+colors.post+'" stroke-width="1.4"/>' +
      '</pattern></defs>' +
      '<rect x="0" y="'+groundY+'" width="'+W+'" height="'+(H-groundY)+'" fill="#D9CFA9"/>' +
      panels + gateMark +
      '<text x="16" y="'+(H-14)+'" font-family="Inter,sans-serif" font-size="12" fill="#5C6D68">'+state.height+' ft height &middot; '+numGates+' gate'+(numGates===1?"":"s")+'</text>';
  }

  /* ---------------- init ---------------- */
  loadRates().then(function(){
    buildFenceTypeOptions();
    buildMaterialGrid();
    buildHeightRow();
    renderGates();
    recalc();
    drawPreview();
    if (leadUnlocked) { switchView("estimate"); } else { switchView("gate"); }
  });
})();
