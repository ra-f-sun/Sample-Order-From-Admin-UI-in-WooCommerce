/**
 * API Client for WooCommerce Sample Orders
 * 
 * Wrapper functions for REST API endpoints using wp.apiFetch
 */

import apiFetch from '@wordpress/api-fetch';

const API_NAMESPACE = '/wcso/v1';

/**
 * Search products by term
 * 
 * @param {string} term - Search term
 * @returns {Promise<Array>} Array of products
 */
export const searchProducts = async (term) => {
  return apiFetch({
    path: `${API_NAMESPACE}/products/search?term=${encodeURIComponent(term)}`,
    method: 'GET',
  });
};

/**
 * Get all products
 * 
 * @returns {Promise<Array>} Array of all products
 */
export const getAllProducts = async () => {
  return apiFetch({
    path: `${API_NAMESPACE}/products`,
    method: 'GET',
  });
};

/**
 * Create a sample order
 * 
 * @param {Object} orderData - Order data including products, billing, shipping, etc.
 * @returns {Promise<Object>} Created order data with order_id and order_url
 */
export const createOrder = async (orderData) => {
  return apiFetch({
    path: `${API_NAMESPACE}/orders`,
    method: 'POST',
    data: orderData,
  });
};

/**
 * Save plugin settings
 * 
 * @param {Object} settings - Settings object with tiers and general settings
 * @returns {Promise<Object>} Success message
 */
export const saveSettings = async (settings) => {
  return apiFetch({
    path: `${API_NAMESPACE}/settings`,
    method: 'POST',
    data: { settings },
  });
};

/**
 * Get email log content
 * 
 * @returns {Promise<Object>} Object with content property
 */
export const getEmailLog = async () => {
  return apiFetch({
    path: `${API_NAMESPACE}/logs/email`,
    method: 'GET',
  });
};

/**
 * Clear email log
 * 
 * @returns {Promise<Object>} Success message
 */
export const clearEmailLog = async () => {
  return apiFetch({
    path: `${API_NAMESPACE}/logs/email`,
    method: 'DELETE',
  });
};

/**
 * Get analytics data
 * 
 * @param {string} startDate - Start date in Y-m-d format
 * @param {string} endDate - End date in Y-m-d format
 * @returns {Promise<Object>} Analytics data for charts
 */
export const getAnalyticsData = async (startDate, endDate) => {
  const params = new URLSearchParams();
  if (startDate) params.append('start_date', startDate);
  if (endDate) params.append('end_date', endDate);
  
  return apiFetch({
    path: `${API_NAMESPACE}/analytics?${params.toString()}`,
    method: 'GET',
  });
};

/**
 * Get drilldown analytics data
 * 
 * @param {string} category - Category filter
 * @param {string} date - Date filter
 * @param {string} statusFilter - Status filter (success/failed)
 * @returns {Promise<Array>} Array of orders
 */
export const getDrilldownData = async (category, date, statusFilter) => {
  const params = new URLSearchParams();
  if (category) params.append('category', category);
  if (date) params.append('date', date);
  if (statusFilter) params.append('status_filter', statusFilter);
  
  return apiFetch({
    path: `${API_NAMESPACE}/analytics/drilldown?${params.toString()}`,
    method: 'GET',
  });
};

/**
 * Backfill analytics data from existing orders
 * 
 * @returns {Promise<Object>} Success message with count
 */
export const analyticsBackfill = async () => {
  return apiFetch({
    path: `${API_NAMESPACE}/analytics/backfill`,
    method: 'POST',
  });
};
