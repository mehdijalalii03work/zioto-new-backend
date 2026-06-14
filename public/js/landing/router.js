const ROUTES = {
  '/': 'home',
  '/about': 'about',
  '/login': 'login',
  '/profile': 'profile',
  '/cart': 'cart',
  '/checkout': 'checkout',
  '/success': 'success',
};

const PRODUCT_ROUTE_PATTERN = /^\/products\/(.+)$/;

function pathToPage(path) {
  const match = path.match(PRODUCT_ROUTE_PATTERN);
  if (match) return { page: 'product', slug: decodeURIComponent(match[1]) };
  const page = ROUTES[path];
  if (page) return { page, slug: null };
  return { page: 'home', slug: null };
}

function pageToPath(page, data) {
  if (page === 'product' && data?.slug) return '/products/' + encodeURIComponent(data.slug);
  if (page === 'home') return '/';
  return '/' + page;
}

function navigateTo(page, data = null, pushState = true) {
  STATE.currentPage = page;
  if (page === 'product' && data) STATE.selectedProduct = data;
  renderPage();
  window.scrollTo({ top: 0, behavior: 'smooth' });

  if (pushState) {
    const path = pageToPath(page, data);
    if (window.location.pathname !== path) {
      history.pushState({ page, slug: data?.slug || null }, '', path);
    }
  }
}

function renderPage() {
  const app = document.getElementById('app');
  switch (STATE.currentPage) {
    case 'home': app.innerHTML = renderHome(); break;
    case 'about': app.innerHTML = renderAbout(); break;
    case 'login': app.innerHTML = renderLogin(); break;
    case 'profile': app.innerHTML = renderProfile(); break;
    case 'product': app.innerHTML = renderProductDetail(STATE.selectedProduct); break;
    case 'cart': app.innerHTML = renderCart(); break;
    case 'checkout': app.innerHTML = renderCheckout(); break;
    case 'success': app.innerHTML = renderSuccess(); break;
    default: app.innerHTML = renderHome();
  }
  updateCartCount();
  animateOnScroll();
}

window.addEventListener('popstate', (e) => {
  if (e.state) {
    if (e.state.page === 'product' && e.state.slug) {
      const product = PRODUCTS.find(p => p.slug === e.state.slug);
      navigateTo('product', product, false);
    } else {
      navigateTo(e.state.page, null, false);
    }
  } else {
    const { page, slug } = pathToPage(window.location.pathname);
    if (page === 'product' && slug) {
      const product = PRODUCTS.find(p => p.slug === slug);
      navigateTo('product', product, false);
    } else {
      navigateTo(page, null, false);
    }
  }
});
