import {useEffect, useState} from 'react';
import {AppState} from 'react-native';

export const staffDayKey = (date = new Date()) => {
  const pad = value => String(value).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

// Refreshes at the local date boundary and when the app returns to the foreground.
export const useStaffDay = () => {
  const [day, setDay] = useState(staffDayKey);

  useEffect(() => {
    const refreshDay = () => setDay(current => {
      const next = staffDayKey();
      return current === next ? current : next;
    });
    const interval = setInterval(refreshDay, 30_000);
    const subscription = AppState.addEventListener('change', state => {
      if (state === 'active') refreshDay();
    });
    refreshDay();
    return () => {
      clearInterval(interval);
      subscription.remove();
    };
  }, []);

  return day;
};
