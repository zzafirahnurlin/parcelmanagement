document.addEventListener('DOMContentLoaded', function () {
  var burger = document.getElementById('burger'), side = document.getElementById('side');
  if (burger && side) burger.addEventListener('click', function (e) { e.stopPropagation(); side.classList.toggle('open'); });
  document.addEventListener('click', function (e) { if (side && !side.contains(e.target)) side.classList.remove('open'); });

  var chip = document.getElementById('userChip'), menu = document.getElementById('userMenu');
  if (chip && menu) {
    chip.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('open'); });
    document.addEventListener('click', function () { menu.classList.remove('open'); });
  }

 document.querySelectorAll('.notice.ok:not([data-keep])').forEach(function (n) {
    setTimeout(function () { n.style.transition = 'opacity .4s'; n.style.opacity = '0'; setTimeout(function () { n.remove(); }, 400); }, 4000);
  });

  document.querySelectorAll('[data-filter]').forEach(function (inp) {
    var table = document.querySelector(inp.getAttribute('data-filter'));
    if (!table) return;
    inp.addEventListener('input', function () {
      var q = inp.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
      });
    });
  });

  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) { if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault(); });
  });
});