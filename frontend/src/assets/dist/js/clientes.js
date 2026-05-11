function abrirModalCliente() {
    document.getElementById('modalCliente').classList.remove('hidden');
    document.getElementById('modalCliente').classList.add('flex');
  }

  function cerrarModalCliente() {
    document.getElementById('modalCliente').classList.add('hidden');
  }

  function toggleFiador() {
    const form = document.getElementById('formFiador');
    form.classList.toggle('hidden');
  }

   function previewImage(input, previewId) {
  const file = input.files[0];
  if (!file) return;

  // Validación estricta
  if (!file.type.startsWith('image/')) {
    alert('Solo se permiten imágenes');
    input.value = '';
    return;
  }

  const reader = new FileReader();
  reader.onload = function (e) {
    const img = document.getElementById(previewId);
    img.src = e.target.result;
    img.classList.remove('opacity-70');
    img.classList.add('opacity-100');
  };
  reader.readAsDataURL(file);
}

function limpiarFormularioCliente() {
  const form = document.querySelector('#modalCliente form');

  // 1. Limpiar inputs texto
  form.querySelectorAll('input[type="text"], input[type="tel"]').forEach(input => {
    input.value = '';
  });

  // 2. Limpiar inputs file
  form.querySelectorAll('input[type="file"]').forEach(input => {
    input.value = '';
  });

  // 3. Restaurar imágenes guía (CLIENTE)
  document.getElementById('prevFotoCliente').src = 'assets/dist/img/documentos/foto.jpg';
  document.getElementById('prevCedulaFrontal').src = 'assets/dist/img/documentos/cedulafrontal.png';
  document.getElementById('prevCedulaAtras').src = 'assets/dist/img/documentos/cedulaatras.png';

  // 4. Restaurar imágenes guía (FIADOR)
  const imgFiador = {
    foto: document.getElementById('prevFotoFiador'),
    frontal: document.getElementById('prevCedulaFrontalFiador'),
    atras: document.getElementById('prevCedulaAtrasFiador')
  };

  if (imgFiador.foto) imgFiador.foto.src = 'assets/dist/img/documentos/foto.jpg';
  if (imgFiador.frontal) imgFiador.frontal.src = 'assets/dist/img/documentos/cedulafrontal.png';
  if (imgFiador.atras) imgFiador.atras.src = 'assets/dist/img/documentos/cedulaatras.png';

  // 5. Ocultar fiador y desmarcar checkbox
  const checkFiador = document.getElementById('checkFiador');
  const formFiador = document.getElementById('formFiador');

  if (checkFiador) checkFiador.checked = false;
  if (formFiador) formFiador.classList.add('hidden');
}

// 🔴 MODIFICA SOLO ESTA FUNCIÓN (no el HTML)
function cerrarModalCliente() {
  limpiarFormularioCliente();        // ← limpia todo
  document.body.classList.remove('overflow-hidden');
  document.getElementById('modalCliente').classList.add('hidden');
}