// Scroll suave para enlaces internos
document.querySelectorAll('a[href^="#"]').forEach(link => {
  link.addEventListener('click', function(e) {
    const target = document.querySelector(this.getAttribute('href'));

    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});

// Formulario de contacto enviado a WhatsApp
const contactForm = document.getElementById('contactForm');

if (contactForm) {
  contactForm.addEventListener('submit', function(e) {
    e.preventDefault();

    const nombre = document.getElementById('nombre').value.trim();
    const telefono = document.getElementById('telefono').value.trim();
    const negocio = document.getElementById('negocio').value.trim();
    const plan = document.getElementById('plan').value;
    const mensaje = document.getElementById('mensaje').value.trim();

    const texto =
      `Hola, quiero información sobre Rutalan.%0A%0A` +
      `Nombre: ${nombre}%0A` +
      `Teléfono: ${telefono}%0A` +
      `Tipo de negocio: ${negocio}%0A` +
      `Plan de interés: ${plan}%0A` +
      `Mensaje: ${mensaje}`;

    const url = `https://wa.me/573209839356?text=${texto}`;
    window.open(url, '_blank');
  });
}
