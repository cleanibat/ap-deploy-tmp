(function () {
  "use strict";
  window.dataLayer = window.dataLayer || [];
  function track(event, params) {
    window.dataLayer.push(Object.assign({ event: event }, params || {}));
  }

  // Année dynamique dans le footer
  var y = document.getElementById("year");
  if (y) y.textContent = new Date().getFullYear();

  // ---- Conversion : clic-to-call ----
  document.querySelectorAll('[data-cta="phone"]').forEach(function (el) {
    el.addEventListener("click", function () {
      track("click_to_call", { conversion_type: "phone" });
    });
  });

  // ---- Slider avant / après ----
  document.querySelectorAll(".ba-slider").forEach(function (slider, idx) {
    var n = idx + 1;
    var input  = document.getElementById("ba" + n + "-input");
    var before = document.getElementById("ba" + n + "-before");
    var handle = document.getElementById("ba" + n + "-handle");
    if (!input || !before || !handle) return;
    function syncW() {
      slider.style.setProperty("--slider-w", slider.offsetWidth + "px");
    }
    function setPos(v) {
      before.style.width = v + "%";
      handle.style.left  = v + "%";
      handle.setAttribute("aria-valuenow", Math.round(v));
    }
    // Init après layout
    setTimeout(function () { syncW(); setPos(50); input.value = 50; }, 0);
    input.addEventListener("input", function () { setPos(this.value); });
    handle.addEventListener("keydown", function (e) {
      var v = parseInt(input.value, 10);
      if (e.key === "ArrowLeft")      { v = Math.max(0, v - 5); }
      else if (e.key === "ArrowRight") { v = Math.min(100, v + 5); }
      else return;
      input.value = v; setPos(v); e.preventDefault();
    });
    window.addEventListener("resize", function () {
      requestAnimationFrame(syncW);
    });
  });

  // ---- Formulaire devis ----
  var form = document.getElementById("quote-form");
  if (form) {
    var started = false;
    // Conversion : début de saisie
    form.addEventListener("focusin", function () {
      if (!started) { started = true; track("form_start", { form_name: "devis" }); }
    });

    var status = document.getElementById("form-status");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      status.className = "form-status";
      status.textContent = "Envoi en cours…";
      var data = new FormData(form);
      fetch(form.action, { method: "POST", body: data })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (res) {
          if (res && res.success) {
            // Conversion principale : lead généré
            track("generate_lead", { form_name: "devis", value: 1, currency: "EUR" });
            status.className = "form-status ok";
            status.textContent = "Merci ! Votre demande est bien partie. Nous vous répondons sous 24h.";
            form.reset();
          } else {
            throw new Error((res && res.message) || "Erreur");
          }
        })
        .catch(function () {
          status.className = "form-status err";
          status.textContent = "Une erreur est survenue. Appelez-nous au 07 69 88 64 54.";
        });
    });
  }
})();

/* ===== Carte zone d'intervention ===== */
(function(){
  if(!document.getElementById('aziMap')) return;
  var SVGNS="http://www.w3.org/2000/svg";
  var cities=[
    {name:'Dunkerque', x:549.9,y:46},
    {name:'Le Touquet',x:494.4,y:98.1},
    {name:'Lille',     x:597.7,y:86.8},
    {name:'Arras',     x:578.0,y:121.6},
    {name:'Amiens',    x:544.3,y:162.4},
    {name:'Reims',     x:665.9,y:226.7},
    {name:'Charleville',x:714.3,y:174.6},
    {name:'Troyes',    x:668.7,y:324.7}
  ];
  function el(tag,attrs,parent){var e=document.createElementNS(SVGNS,tag);for(var k in attrs)e.setAttribute(k,attrs[k]);if(parent)parent.appendChild(e);return e;}
  var g=document.getElementById('aziCities');
  cities.forEach(function(c,i){
    var hy=c.y-26, sy1=c.y-19;
    var grp=el('g',{class:'azi-city'},g);

    // pin (drops in)
    var pin=el('g',{class:'azi-pin',style:'animation-delay:'+(0.3+i*0.11).toFixed(2)+'s'},grp);
    el('line',{x1:c.x,y1:sy1,x2:c.x,y2:c.y,stroke:'var(--pin)','stroke-width':2},pin);
    el('circle',{cx:c.x,cy:hy,r:7,fill:'var(--pin)'},pin);
    el('circle',{cx:c.x,cy:hy,r:2.4,fill:'var(--ink)'},pin);
    el('circle',{cx:c.x,cy:c.y,r:3,fill:'var(--pin)'},pin);

    // hover chip (city name)
    var chip=el('g',{class:'azi-chip'},grp);
    var w=Math.round(c.name.length*8.1+22), ch=27;
    var top=(hy-12-ch); if(top<4) top=c.y+12;            // clamp under pin for top cities
    var cx=Math.min(Math.max(c.x, w/2+2), 998-w/2);       // keep inside frame
    el('rect',{x:cx-w/2,y:top,width:w,height:ch,rx:5,fill:'var(--accent)'},chip);
    var t=el('text',{x:cx,y:top+ch/2+1,'text-anchor':'middle','dominant-baseline':'middle',class:'azi-chiptxt'},chip);
    t.textContent=c.name;

    // enlarged invisible hit target
    el('circle',{cx:c.x,cy:c.y-13,r:24,fill:'#fff','fill-opacity':0},grp);
  });

  var map=document.getElementById('aziMap');
  function play(){ map.classList.remove('play'); void map.offsetWidth; map.classList.add('play'); }
  map.addEventListener('mouseenter',play);
  // joue à l'apparition à l'écran
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){play();io.disconnect();}});},{threshold:0.35});
    io.observe(map);
  } else { play(); }
  // Fallback : si la carte est déjà visible au chargement (ex. dans le hero), on lance l'animation
  // (setTimeout plutôt que requestAnimationFrame pour se déclencher même hors premier plan)
  setTimeout(function(){
    var r=map.getBoundingClientRect(), vh=window.innerHeight||document.documentElement.clientHeight;
    if(r.top < vh*0.95 && r.bottom > 0 && !map.classList.contains('play')) play();
  }, 250);
})();

// Scroll reveal pour les étapes
(function(){
  var els = document.querySelectorAll('[data-reveal]');
  if (!els.length) return;
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (e.isIntersecting) {
          var el = e.target;
          var delay = parseInt(el.getAttribute('data-reveal-delay') || '0', 10);
          setTimeout(function(){ el.classList.add('is-revealed'); }, delay);
          io.unobserve(el);
        }
      });
    }, {threshold: 0.2});
    els.forEach(function(el){ io.observe(el); });
  } else {
    els.forEach(function(el){ el.classList.add('is-revealed'); });
  }
})();

// Masquer la barre mobile quand le formulaire est dans le viewport
(function(){
  var bar = document.querySelector('.mobile-bar');
  var devis = document.getElementById('devis');
  if (!bar || !devis || !('IntersectionObserver' in window)) return;
  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      bar.classList.toggle('mobile-bar--hidden', e.isIntersecting);
    });
  }, {threshold: 0.15});
  io.observe(devis);
})();
