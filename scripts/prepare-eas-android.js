const fs = require('node:fs');
const path = require('node:path');

function productionApiUrl(value) {
  let url;
  try {
    url = new URL(value || '');
  } catch {
    throw new Error('Set MOBILE_API_URL in the EAS production environment to the public HTTPS API URL ending in /api/v1.');
  }

  const host = url.hostname.toLowerCase();
  const privateHost = host === 'localhost' || host === '127.0.0.1' || host === '0.0.0.0'
    || host === '[::1]' || host.endsWith('.local')
    || /^10\./.test(host) || /^192\.168\./.test(host)
    || /^172\.(1[6-9]|2\d|3[01])\./.test(host);

  if (url.protocol !== 'https:' || privateHost || url.username || url.password
      || url.search || url.hash || !url.pathname.replace(/\/$/, '').endsWith('/api/v1')) {
    throw new Error('MOBILE_API_URL must be a public HTTPS URL ending in /api/v1, without credentials, query, or fragment.');
  }

  return url.toString().replace(/\/$/, '');
}

if (require.main === module) {
  try {
    if (process.env.EAS_BUILD_PLATFORM !== 'android') {
      throw new Error('This build hook is only for EAS Android builds.');
    }
    if (!/^[1-9]\d*$/.test(process.env.ANDROID_VERSION_CODE || '')) {
      throw new Error('Set ANDROID_VERSION_CODE in the EAS production environment above the previous release versionCode.');
    }

    const apiUrl = productionApiUrl(process.env.MOBILE_API_URL);
    fs.writeFileSync(path.join(__dirname, '..', '.env.production'), `API_URL=${apiUrl}\n`, {mode: 0o600});
    console.log('Prepared the EAS Android release API configuration.');
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}

module.exports = {productionApiUrl};
