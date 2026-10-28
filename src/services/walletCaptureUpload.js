// A failed batch is retried item by item only for permanent validation or
// identifier conflicts. Network, authorization, and server failures remain
// pending so a later sync can retry them without losing a notification.
export const uploadWalletCaptureBatch = async (captures, post, markSynced, markRejected) => {
  try {
    const response = await post(captures);
    const savedIds = response.data?.saved_ids || [];
    if (savedIds.length !== captures.length
        || !captures.every(capture => savedIds.includes(capture.client_id))) {
      throw new Error('The server did not acknowledge every wallet capture.');
    }
    await markSynced(savedIds);
    return {uploaded: savedIds.length, rejected: 0};
  } catch (batchError) {
    const status = batchError?.response?.status;
    if (status !== 409 && status !== 422) throw batchError;
  }

  let uploaded = 0;
  let rejected = 0;
  for (const capture of captures) {
    try {
      const response = await post([capture]);
      if (!response.data?.saved_ids?.includes(capture.client_id)) {
        throw new Error('The server did not acknowledge this wallet capture.');
      }
      await markSynced([capture.client_id]);
      uploaded += 1;
    } catch (error) {
      const status = error?.response?.status;
      if (status !== 409 && status !== 422) throw error;
      const reason = status === 409
        ? 'Notification identifier conflicted with another account or capture.'
        : 'Notification format was rejected. Check the captured wallet alert before retrying.';
      await markRejected(capture.client_id, reason);
      rejected += 1;
    }
  }
  return {uploaded, rejected};
};
