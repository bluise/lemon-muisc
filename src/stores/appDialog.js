import { reactive } from 'vue'

/** 全局应用内确认 / 提示弹窗（替代 window.confirm / alert） */
export const appDialogState = reactive({
  open: false,
  mode: 'confirm', // confirm | alert
  title: '确认',
  message: '',
  hint: '',
  cover: '',
  confirmText: '确定',
  cancelText: '取消',
  danger: false,
  busy: false,
  busyText: '处理中…',
})

let resolver = null

function settle(result) {
  appDialogState.open = false
  appDialogState.busy = false
  const resolve = resolver
  resolver = null
  if (resolve) resolve(result)
}

/**
 * @param {{
 *   title?: string,
 *   message?: string,
 *   hint?: string,
 *   cover?: string,
 *   confirmText?: string,
 *   cancelText?: string,
 *   danger?: boolean,
 * }} options
 * @returns {Promise<boolean>}
 */
export function appConfirm(options = {}) {
  return new Promise((resolve) => {
    if (resolver) settle(false)
    resolver = resolve
    appDialogState.open = true
    appDialogState.mode = 'confirm'
    appDialogState.title = options.title || '确认'
    appDialogState.message = options.message || ''
    appDialogState.hint = options.hint || ''
    appDialogState.cover = options.cover || ''
    appDialogState.confirmText = options.confirmText || '确定'
    appDialogState.cancelText = options.cancelText || '取消'
    appDialogState.danger = Boolean(options.danger)
    appDialogState.busy = false
    appDialogState.busyText = options.busyText || '处理中…'
  })
}

/**
 * @param {{ title?: string, message?: string, hint?: string, confirmText?: string }} options
 * @returns {Promise<void>}
 */
export function appAlert(options = {}) {
  return new Promise((resolve) => {
    if (resolver) settle(undefined)
    resolver = () => resolve()
    appDialogState.open = true
    appDialogState.mode = 'alert'
    appDialogState.title = options.title || '提示'
    appDialogState.message = options.message || ''
    appDialogState.hint = options.hint || ''
    appDialogState.cover = ''
    appDialogState.confirmText = options.confirmText || '知道了'
    appDialogState.cancelText = ''
    appDialogState.danger = false
    appDialogState.busy = false
  })
}

export function confirmAppDialog() {
  if (appDialogState.busy) return
  if (appDialogState.mode === 'alert') settle(undefined)
  else settle(true)
}

export function cancelAppDialog() {
  if (appDialogState.busy) return
  if (appDialogState.mode === 'alert') settle(undefined)
  else settle(false)
}
