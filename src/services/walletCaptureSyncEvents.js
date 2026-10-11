const listeners = new Set();

export const subscribeWalletCaptureSync = listener => {
  listeners.add(listener);
  return () => listeners.delete(listener);
};

export const emitWalletCaptureSync = () => {
  listeners.forEach(listener => listener());
};
