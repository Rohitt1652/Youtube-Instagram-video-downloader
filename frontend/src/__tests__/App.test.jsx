import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import App from '../App';
import * as client from '../api/client';

vi.mock('../api/client', () => ({
  getMediaInfo: vi.fn(),
  createMediaDownload: vi.fn(),
  getJobStatus: vi.fn(),
  checkHealth: vi.fn(),
}));

describe('MediaGrab Frontend Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
  });

  it('renders hero title and input form correctly', () => {
    render(<App />);

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(/Download Your Media/i);
    expect(screen.getByPlaceholderText(/Paste a YouTube or Instagram URL/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Fetch Media/i })).toBeInTheDocument();
  });

  it('validates URL input and rejects non-HTTPS or empty URLs', async () => {
    render(<App />);

    const input = screen.getByPlaceholderText(/Paste a YouTube or Instagram URL/i);

    // Submit with HTTP (insecure)
    fireEvent.change(input, { target: { value: 'http://youtube.com/watch?v=123' } });
    fireEvent.submit(input.closest('form'));

    await waitFor(() => {
      expect(screen.getByText(/Only secure HTTPS URLs are permitted/i)).toBeInTheDocument();
    });
  });

  it('shows loading state while fetching media info', async () => {
    client.getMediaInfo.mockReturnValue(new Promise(() => {})); // Never resolves to check loading

    render(<App />);
    const input = screen.getByPlaceholderText(/Paste a YouTube or Instagram URL/i);

    fireEvent.change(input, { target: { value: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' } });
    fireEvent.submit(input.closest('form'));

    expect(screen.getByText(/Fetching.../i)).toBeInTheDocument();
  });

  it('displays API error properly when extraction fails', async () => {
    client.getMediaInfo.mockRejectedValue({
      response: {
        data: {
          success: false,
          error: {
            code: 'UNSUPPORTED_URL',
            message: 'This domain is not supported.',
          },
        },
      },
    });

    render(<App />);
    const input = screen.getByPlaceholderText(/Paste a YouTube or Instagram URL/i);

    fireEvent.change(input, { target: { value: 'https://vimeo.com/12345' } });
    fireEvent.submit(input.closest('form'));

    await waitFor(() => {
      expect(screen.getByText(/This domain is not supported/i)).toBeInTheDocument();
    });
  });

  it('renders media preview and format selection on successful info retrieval', async () => {
    const mockMedia = {
      platform: 'youtube',
      title: 'Rick Astley - Never Gonna Give You Up',
      thumbnail: 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
      duration: 213,
      author: 'RickAstleyVEVO',
      formats: [
        { id: 'video_1080p', type: 'video', quality: '1080p', extension: 'mp4', filesize: 25000000 },
        { id: 'video_720p', type: 'video', quality: '720p', extension: 'mp4', filesize: 15000000 },
        { id: 'audio_mp3', type: 'audio', quality: 'MP3 Audio (192kbps)', extension: 'mp3', filesize: null },
      ],
    };

    client.getMediaInfo.mockResolvedValue({
      success: true,
      data: mockMedia,
    });

    render(<App />);
    const input = screen.getByPlaceholderText(/Paste a YouTube or Instagram URL/i);

    fireEvent.change(input, { target: { value: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' } });
    fireEvent.submit(input.closest('form'));

    await waitFor(() => {
      expect(screen.getByText('Rick Astley - Never Gonna Give You Up')).toBeInTheDocument();
      expect(screen.getByText('1080p')).toBeInTheDocument();
      expect(screen.getByText('720p')).toBeInTheDocument();
      expect(screen.getByText('MP3 Audio (192kbps)')).toBeInTheDocument();
    });

    // Test format selection click
    const audioBtn = screen.getByText('MP3 Audio (192kbps)');
    fireEvent.click(audioBtn);

    expect(screen.getByText(/Download MP3 Audio/i)).toBeInTheDocument();
  });

  it('triggers download process and displays download progress modal', async () => {
    const mockMedia = {
      platform: 'youtube',
      title: 'Authorized Creative Commons Reel',
      thumbnail: 'https://example.com/thumb.jpg',
      duration: 60,
      author: 'Author',
      formats: [
        { id: 'video_720p', type: 'video', quality: '720p', extension: 'mp4' },
      ],
    };

    client.getMediaInfo.mockResolvedValue({ success: true, data: mockMedia });
    client.createMediaDownload.mockResolvedValue({ success: true, job_id: 'test-uuid-1234' });
    client.getJobStatus.mockResolvedValue({
      success: true,
      data: {
        status: 'processing',
        progress: 45,
      },
    });

    render(<App />);
    const input = screen.getByPlaceholderText(/Paste a YouTube or Instagram URL/i);
    fireEvent.change(input, { target: { value: 'https://www.youtube.com/watch?v=authorized' } });
    fireEvent.submit(input.closest('form'));

    await waitFor(() => {
      expect(screen.getByText('Authorized Creative Commons Reel')).toBeInTheDocument();
    });

    const downloadBtn = screen.getByRole('button', { name: /Download 720p/i });
    fireEvent.click(downloadBtn);

    await waitFor(() => {
      expect(client.createMediaDownload).toHaveBeenCalledWith('https://www.youtube.com/watch?v=authorized', 'video_720p');
    });

    await waitFor(() => {
      expect(screen.getByText('Processing Media')).toBeInTheDocument();
      expect(screen.getByText('45%')).toBeInTheDocument();
    }, { timeout: 3000 });
  });
});
