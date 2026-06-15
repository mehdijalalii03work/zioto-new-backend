document.addEventListener('DOMContentLoaded', () => {
  fetch('/api/profile', { headers: { 'Accept': 'application/json' } })
    .then(res => {
      if (res.status === 401) throw new Error('not authenticated');
      return res.json();
    })
    .then(data => {
      if (data?.user) {
        const u = data.user;
        STATE.isLoggedIn = true;
        STATE.userData = {
          first_name: u.first_name || '',
          last_name: u.last_name || '',
          name: u.name || '',
          phone: u.phone || '',
          email: u.email || '',
          nationalId: u.national_id || '',
          birthDate: u.birth_date || '',
          joinDate: u.created_at ? new Date(u.created_at).toLocaleDateString('fa-IR', { year: 'numeric', month: '2-digit', day: '2-digit' }) : '',
        };
      }
    })
    .catch(() => {})
    .finally(() => {
      const { page, slug } = pathToPage(window.location.pathname);
      if (page === 'product' && slug) {
        const product = PRODUCTS.find(p => p.slug === slug);
        navigateTo('product', product, false);
      } else {
        navigateTo(page, null, false);
      }
      updateAuthButtons();
      updateActiveNav();
    });

  window.addEventListener('scroll', () => {
    const header = document.getElementById('main-header');
    if (window.scrollY > 50) {
      header.classList.add('bg-[#111318]/95', 'backdrop-blur-sm', 'shadow-lg');
    } else {
      header.classList.remove('bg-[#111318]/95', 'backdrop-blur-sm', 'shadow-lg');
    }
  });
});
