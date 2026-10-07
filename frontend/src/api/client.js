import axios from 'axios';

// Render production backend URL by default, or overridden by VITE_API_URL
const API_BASE_URL =
  import.meta.env.VITE_API_URL ||
  'https://youtube-instagram-video-downloader-l3e3.onrender.com/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 60000,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

export const getMediaInfo = async (url) => {
  const response = await api.post('/media/info', { url });
  return response.data;
};

export const createMediaDownload = async (url, formatId) => {
  const response = await api.post('/media/download', { url, format_id: formatId });
  return response.data;
};

export const getJobStatus = async (jobId) => {
  const response = await api.get(`/media/jobs/${jobId}`);
  return response.data;
};

export const checkHealth = async () => {
  const response = await api.get('/media/health');
  return response.data;
};

export default api;
