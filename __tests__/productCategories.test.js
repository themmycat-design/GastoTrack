import {productCategoriesFromOptions, productCategoryTabs, productsInCategory} from '../src/constants/productCategories';

describe('product category choices', () => {
  const options = [
    {kind: 'category', transaction_type: 'income', name: 'Sales'},
    {kind: 'category', transaction_type: 'product', name: 'Coffee'},
    {kind: 'category', transaction_type: 'product', name: 'Bakery'},
    {kind: 'category', transaction_type: 'product', name: 'coffee'},
  ];

  it('uses defaults until business options load', () => {
    expect(productCategoriesFromOptions([], false)).toContain('Milktea');
  });

  it('uses only distinct business product categories after loading', () => {
    expect(productCategoriesFromOptions(options, true)).toEqual(['Coffee', 'Bakery']);
  });

  it('does not bring deleted defaults back when no categories remain', () => {
    expect(productCategoriesFromOptions([], true)).toEqual([]);
  });

  it('shows saved categories even before products are added', () => {
    expect(productCategoryTabs(productCategoriesFromOptions(options, true)))
      .toEqual(['All', 'Coffee', 'Bakery']);
  });

  it('filters both Home and ordering products by the selected saved category', () => {
    const products = [
      {id: 1, category: 'Coffee'},
      {id: 2, category: 'Bakery'},
    ];
    expect(productsInCategory(products, 'bakery')).toEqual([products[1]]);
    expect(productsInCategory(products, 'All')).toEqual(products);
    expect(productsInCategory(products, 'Milktea')).toEqual([]);
  });
});
