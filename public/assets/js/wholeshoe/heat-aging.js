/**
 * Whole Shoe Test Method: Heat Aging (70°C)
 */
window.WholeshoeMethods = window.WholeshoeMethods || {};
window.WholeshoeMethods['heat-aging'] = {
  key: 'heat-aging',
  label: 'Heat Aging',
  unit: 'Hrs',
  defaultMachine: 'OVEN-01',
  isTimed: true,
  duration: 3 * 86400,
  nominal: '3 Days',
  isDailyCheckpoint: true,
  isFlex: false,
};
