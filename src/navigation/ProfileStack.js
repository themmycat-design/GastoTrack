import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import ProfileScreen from '../screens/staff/ProfileScreen';
import ProfileSettingsScreen from '../screens/staff/ProfileSettingsScreen';
import TransactionOptionsScreen from '../screens/staff/TransactionOptionsScreen';
import PersonalInformationScreen from '../screens/staff/PersonalInformationScreen';
import PermissionsScreen from '../screens/staff/PermissionsScreen';
import AboutScreen from '../screens/staff/AboutScreen';
import BugReportsScreen from '../screens/staff/BugReportsScreen';
import YourReportsScreen from '../screens/staff/YourReportsScreen';
import HelpScreen from '../screens/staff/HelpScreen';
import TermsScreen from '../screens/staff/TermsScreen';
import PrivacyPolicyScreen from '../screens/staff/PrivacyPolicyScreen';

const Stack = createNativeStackNavigator();

const ProfileStack = () => (
  <Stack.Navigator screenOptions={{headerShown: false}}>
    <Stack.Screen name="ProfileMain" component={ProfileScreen} />
    <Stack.Screen name="ProfileSettings" component={ProfileSettingsScreen} />
    <Stack.Screen name="PersonalInformation" component={PersonalInformationScreen} />
    <Stack.Screen name="Permissions" component={PermissionsScreen} />
    <Stack.Screen name="Help" component={HelpScreen} />
    <Stack.Screen name="About" component={AboutScreen} />
    <Stack.Screen name="BugReports" component={BugReportsScreen} />
    <Stack.Screen name="YourReports" component={YourReportsScreen} />
    <Stack.Screen name="Terms" component={TermsScreen} />
    <Stack.Screen name="PrivacyPolicy" component={PrivacyPolicyScreen} />
    <Stack.Screen name="CategorySettings" component={TransactionOptionsScreen} initialParams={{kind: 'category'}} />
    <Stack.Screen name="SourceSettings" component={TransactionOptionsScreen} initialParams={{kind: 'source'}} />
  </Stack.Navigator>
);

export default ProfileStack;
