import { useState, useEffect } from 'react';

/**
 * useDebounce hook
 * Delays updating the debounced value until after the specified delay has elapsed
 * since the last time the value was updated.
 *
 * @param {*} value The value to debounce (e.g. search query)
 * @param {number} delay Milliseconds to delay (default: 300ms)
 * @returns {*} The debounced value
 */
export function useDebounce(value, delay = 300) {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);

  return debouncedValue;
}

export default useDebounce;
