import { useEffect, useRef } from 'react'

/**
 * Automatically triggers `onTimeout` after the user has been inactive for
 * `timeout` milliseconds. Inactivity is measured by listening to common
 * browser activity events (mouse, keyboard, scroll, touch). Any activity
 * resets the timer.
 *
 * When `enabled` is false the timer is not armed (e.g. when logged out).
 *
 * @param {object}   options
 * @param {number}   options.timeout   Idle duration in milliseconds.
 * @param {Function} options.onTimeout Callback invoked once the user is idle.
 * @param {boolean}  [options.enabled] Whether the timer should be active.
 * @param {string}   [options.storageKey] Local storage key for persisting activity.
 */
export default function useIdleTimeout({ timeout, onTimeout, enabled = true, storageKey }) {
  const timeoutRef = useRef(null)
  const callbackRef = useRef(onTimeout)
  callbackRef.current = onTimeout

  useEffect(() => {
    if (!enabled) {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current)
        timeoutRef.current = null
      }
      if (storageKey) {
        localStorage.removeItem(storageKey)
      }
      return undefined
    }

    const lastActivity = storageKey ? Number(localStorage.getItem(storageKey)) : 0
    const elapsed = lastActivity ? Date.now() - lastActivity : 0

    if (elapsed >= timeout) {
      callbackRef.current()
      return undefined
    }

    const events = ['mousemove', 'mousedown', 'keydown', 'wheel', 'touchstart']

    const reset = () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current)
      }
      if (storageKey) {
        localStorage.setItem(storageKey, String(Date.now()))
      }
      timeoutRef.current = setTimeout(() => callbackRef.current(), timeout)
    }

    if (lastActivity) {
      timeoutRef.current = setTimeout(() => callbackRef.current(), timeout - elapsed)
    } else {
      reset()
    }

    events.forEach((event) => {
      window.addEventListener(event, reset, { passive: true })
    })

    return () => {
      events.forEach((event) => {
        window.removeEventListener(event, reset)
      })
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current)
        timeoutRef.current = null
      }
    }
  }, [timeout, enabled, storageKey])
}
