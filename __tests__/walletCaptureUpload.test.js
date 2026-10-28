import {uploadWalletCaptureBatch} from '../src/services/walletCaptureUpload';

const captures = [
  {client_id: 'first'}, {client_id: 'bad'}, {client_id: 'later'},
];

describe('wallet capture upload', () => {
  it('quarantines a rejected capture and continues with later valid captures', async () => {
    const post = jest.fn()
      .mockRejectedValueOnce({response: {status: 422}})
      .mockResolvedValueOnce({data: {saved_ids: ['first']}})
      .mockRejectedValueOnce({response: {status: 422}})
      .mockResolvedValueOnce({data: {saved_ids: ['later']}});
    const synced = jest.fn().mockResolvedValue();
    const rejected = jest.fn().mockResolvedValue();

    await expect(uploadWalletCaptureBatch(captures, post, synced, rejected))
      .resolves.toEqual({uploaded: 2, rejected: 1});
    expect(synced.mock.calls).toEqual([[['first']], [['later']]]);
    expect(rejected).toHaveBeenCalledWith('bad', expect.stringContaining('format was rejected'));
  });

  it('keeps the whole batch pending after a temporary failure or lost response', async () => {
    const synced = jest.fn();
    const rejected = jest.fn();
    const networkFailure = new Error('Connection lost');
    await expect(uploadWalletCaptureBatch(captures, jest.fn().mockRejectedValue(networkFailure), synced, rejected))
      .rejects.toBe(networkFailure);
    expect(synced).not.toHaveBeenCalled();
    expect(rejected).not.toHaveBeenCalled();

    const noAcknowledgement = jest.fn().mockResolvedValue({data: {saved_ids: ['first']}});
    await expect(uploadWalletCaptureBatch(captures, noAcknowledgement, synced, rejected))
      .rejects.toThrow('did not acknowledge');
    expect(synced).not.toHaveBeenCalled();
  });

  it('acknowledges an idempotent complete server response once', async () => {
    const synced = jest.fn().mockResolvedValue();
    const rejected = jest.fn();
    const post = jest.fn().mockResolvedValue({data: {saved_ids: captures.map(item => item.client_id)}});
    await expect(uploadWalletCaptureBatch(captures, post, synced, rejected))
      .resolves.toEqual({uploaded: 3, rejected: 0});
    expect(synced).toHaveBeenCalledTimes(1);
    expect(rejected).not.toHaveBeenCalled();
  });
});
