document.addEventListener('DOMContentLoaded', () => {
  const { page, slug } = pathToPage(window.location.pathname);

  if (page === 'product' && slug) {
    const product = PRODUCTS.find(p => p.slug === slug);
    navigateTo('product', product, false);
  } else {
    navigateTo(page, null, false);
  }

  updateAuthButtons();

  window.addEventListener('scroll', () => {
    const header = document.getElementById('main-header');
    if (window.scrollY > 50) {
      header.classList.add('bg-[#111318]/95', 'backdrop-blur-sm', 'shadow-lg');
    } else {
      header.classList.remove('bg-[#111318]/95', 'backdrop-blur-sm', 'shadow-lg');
    }
  });
});
