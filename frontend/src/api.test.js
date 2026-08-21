import { describe, it, expect } from 'vitest';
import { fmt } from './api';

describe('fmt', () => {
  it('formats INR', () => {
    expect(fmt(3600000)).toContain('36');
  });
});
