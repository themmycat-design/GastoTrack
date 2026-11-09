import {createContext, useContext} from 'react';

export const StaffPinContext = createContext(null);

export const useStaffPin = () => useContext(StaffPinContext);
