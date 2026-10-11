export const DEFAULT_PRODUCT_CATEGORIES = ['Milktea', 'Coffee', 'Food', 'Drinks', 'Other'];

export const productCategoriesFromOptions = (options, loaded) => {
  if (!loaded) return DEFAULT_PRODUCT_CATEGORIES;
  const seen = new Set();
  return options.filter(option => option.kind === 'category' && option.transaction_type === 'product')
    .map(option => String(option.name || '').trim())
    .filter(name => {
      const key = name.toLowerCase();
      if (!key || seen.has(key)) return false;
      seen.add(key);
      return true;
    });
};

export const productCategoryTabs = categories => ['All', ...categories];

export const productsInCategory = (products, category) => category === 'All'
  ? products
  : products.filter(product => product.category?.trim().toLowerCase() === category.toLowerCase());
