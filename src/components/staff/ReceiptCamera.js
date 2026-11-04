import React, {useRef, useState} from 'react';
import {ActivityIndicator, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {Camera, useCameraDevice, usePhotoOutput} from 'react-native-vision-camera';
import {COLORS, RADIUS} from '../../theme';

const ReceiptCamera = ({onClose, onCapture}) => {
  const insets = useSafeAreaInsets();
  const camera = useRef(null);
  const device = useCameraDevice('back');
  const photoOutput = usePhotoOutput();
  const [ready, setReady] = useState(false);
  const [capturing, setCapturing] = useState(false);
  const [torch, setTorch] = useState(false);
  const [cameraError, setCameraError] = useState('');
  const [cameraKey, setCameraKey] = useState(0);

  const retryCamera = () => {
    setReady(false);
    setCameraError('');
    setCameraKey(current => current + 1);
  };

  const capture = async () => {
    if (!ready || capturing || cameraError || !camera.current) return;
    setCapturing(true);
    try {
      const photo = await photoOutput.capturePhotoToFile({flashMode: 'off'}, {});
      if (!photo?.filePath) throw new Error('The camera did not save a photo.');
      onCapture(`file://${photo.filePath}`);
    } catch {
      setCameraError('Could not take a photo. Keep the receipt in view and try again.');
    } finally {
      setCapturing(false);
    }
  };

  return <View style={styles.page}>
    {device ? <Camera
      key={cameraKey}
      ref={camera}
      style={StyleSheet.absoluteFill}
      implementationMode="compatible"
      device={device}
      outputs={[photoOutput]}
      isActive
      torchMode={torch ? 'on' : 'off'}
      enableNativeTapToFocusGesture
      onStarted={() => {setReady(true); setCameraError('');}}
      onStopped={() => setReady(false)}
      onError={() => {setReady(false); setCameraError('Camera unavailable. Close and try again.');}}
    /> : null}

    <View style={[styles.topBar, {paddingTop: Math.max(insets.top, 16) + 8}]}>
      <TouchableOpacity style={styles.topButton} accessibilityRole="button" accessibilityLabel="Close receipt camera" onPress={onClose}>
        <Icon name="close" size={25} color={COLORS.textWhite} />
      </TouchableOpacity>
      <Text style={styles.title}>Scan receipt</Text>
      <TouchableOpacity style={[styles.topButton, (!device?.hasTorch || !ready) && styles.disabled]}
        accessibilityRole="button" accessibilityLabel={torch ? 'Turn flash off' : 'Turn flash on'}
        accessibilityState={{disabled: !device?.hasTorch || !ready}}
        disabled={!device?.hasTorch || !ready} onPress={() => setTorch(current => !current)}>
        <Icon name={torch ? 'flash' : 'flash-off'} size={23} color={COLORS.textWhite} />
      </TouchableOpacity>
    </View>

    <View style={styles.guide} pointerEvents="none">
      <Text style={styles.guideTitle}>Fit the whole receipt inside the frame</Text>
      <View style={styles.frame}>
        <View style={[styles.corner, styles.topLeft]} />
        <View style={[styles.corner, styles.topRight]} />
        <View style={[styles.corner, styles.bottomLeft]} />
        <View style={[styles.corner, styles.bottomRight]} />
      </View>
      <Text style={styles.guideHint}>Keep the text flat, sharp, and well lit.</Text>
    </View>

    <View style={[styles.bottomBar, {paddingBottom: Math.max(insets.bottom, 16) + 16}]}>
      {cameraError ? <View style={styles.errorRow}>
        <Text style={styles.error} accessibilityRole="alert">{cameraError}</Text>
        <TouchableOpacity accessibilityRole="button" accessibilityLabel="Retry camera" onPress={retryCamera}>
          <Text style={styles.retryText}>Try again</Text>
        </TouchableOpacity>
      </View>
        : !device ? <Text style={styles.status}>No back camera found on this device.</Text>
          : !ready ? <Text style={styles.status}>Starting camera...</Text> : <Text style={styles.status}>Tap the receipt to focus</Text>}
      <TouchableOpacity style={[styles.shutter, (!ready || capturing || !!cameraError) && styles.disabled]}
        accessibilityRole="button" accessibilityLabel="Capture receipt"
        accessibilityState={{disabled: !ready || capturing || !!cameraError, busy: capturing}}
        disabled={!ready || capturing || !!cameraError} onPress={capture}>
        {capturing ? <ActivityIndicator color={COLORS.accentDark} /> : <View style={styles.shutterInner} />}
      </TouchableOpacity>
      <Text style={styles.bottomHint}>The scan starts after capture.</Text>
    </View>
  </View>;
};

const styles = StyleSheet.create({
  page: {flex: 1, backgroundColor: COLORS.textDark},
  topBar: {flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingBottom: 16, backgroundColor: 'rgba(10,46,42,0.82)'},
  topButton: {width: 48, height: 48, alignItems: 'center', justifyContent: 'center', borderRadius: RADIUS.pill},
  title: {color: COLORS.textWhite, fontSize: 17, fontWeight: '800'},
  disabled: {opacity: 0.42},
  guide: {flex: 1, justifyContent: 'center', paddingHorizontal: 28},
  guideTitle: {color: COLORS.textWhite, fontSize: 16, fontWeight: '800', textAlign: 'center', marginBottom: 18, textShadowColor: COLORS.textDark, textShadowRadius: 8},
  frame: {height: '68%', maxHeight: 530, minHeight: 240, position: 'relative'},
  corner: {position: 'absolute', width: 35, height: 35, borderColor: COLORS.accent, borderWidth: 4},
  topLeft: {top: 0, left: 0, borderRightWidth: 0, borderBottomWidth: 0, borderTopLeftRadius: 12},
  topRight: {top: 0, right: 0, borderLeftWidth: 0, borderBottomWidth: 0, borderTopRightRadius: 12},
  bottomLeft: {bottom: 0, left: 0, borderRightWidth: 0, borderTopWidth: 0, borderBottomLeftRadius: 12},
  bottomRight: {bottom: 0, right: 0, borderLeftWidth: 0, borderTopWidth: 0, borderBottomRightRadius: 12},
  guideHint: {color: COLORS.textWhite, fontSize: 13, textAlign: 'center', marginTop: 18, textShadowColor: COLORS.textDark, textShadowRadius: 8},
  bottomBar: {alignItems: 'center', paddingTop: 16, backgroundColor: 'rgba(10,46,42,0.88)'},
  status: {color: COLORS.textWhite, fontSize: 13, minHeight: 20},
  error: {color: COLORS.textWhite, fontSize: 13, textAlign: 'center', minHeight: 20, paddingHorizontal: 18},
  errorRow: {alignItems: 'center'},
  retryText: {color: COLORS.accent, fontSize: 13, fontWeight: '700', padding: 8},
  shutter: {width: 74, height: 74, borderRadius: 37, borderWidth: 4, borderColor: COLORS.textWhite, alignItems: 'center', justifyContent: 'center', marginVertical: 12},
  shutterInner: {width: 56, height: 56, borderRadius: 28, backgroundColor: COLORS.textWhite},
  bottomHint: {color: COLORS.textWhite, fontSize: 12},
});

export default ReceiptCamera;
