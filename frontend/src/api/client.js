import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  timeout: 30000,
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
