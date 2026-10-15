/**
 * API service configuration
 */
import axios from 'axios';
import { API_BASE_URL } from '../utils/config';

// Configure axios defaults
axios.defaults.withCredentials = true;

/**
 * Create an axios instance with default configuration
 */
const apiClient = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json'
  }
});

export default apiClient;

