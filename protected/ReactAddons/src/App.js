import React, {useRef, useState, useEffect} from 'react'
import {BrowserRouter as Router, Switch, Route, Redirect, useRouteMatch, useLocation} from "react-router-dom";
import {
  isLoggedIn, setToken, setTokenInfo, refreshToken, clearToken,
  getTokenInfo, getTokenTimeBeforeRefresh, refreshTimeout_ms
} from "./helpers";
import {LOGIN_PAGE, MAX_REFRESH_ATTEMPTS} from "./constants";

const PrivateRoute = ({children, ...rest}) => {
  return (
    <Route {...rest} render={() => {
      return isLoggedIn() ? children : window.location.assign(LOGIN_PAGE)
    }} />
  )
}

const App = () => {
  const [tokenState, setTokenState] = useState({expTime: 0, resCode: 200, refreshAttempts: MAX_REFRESH_ATTEMPTS})
  const timerId = useRef(null)

  useEffect(() => {
    const {expTime, resCode, refreshAttempts} = tokenState

    async function tokenRefreshHandler() {
      const {token, tokenPayload, errorCode, errorMessage} = await refreshToken()
      if (!errorCode) {
        setToken(token)
        setTokenInfo(JSON.stringify(tokenPayload))
        setTokenState({expTime: tokenPayload.exp, resCode: 200, refreshAttempts: MAX_REFRESH_ATTEMPTS})
      } else if (errorCode === 401) {
        clearToken()
        console.log(`Auth error ${errorCode}: ${errorMessage}`)
        setTokenState({expTime: false, resCode: errorCode, refreshAttempts: MAX_REFRESH_ATTEMPTS})
      } else {
        console.log(`Auth error ${errorCode}: ${errorMessage}`)
        const tokenPayload = JSON.parse(getTokenInfo())

        if (getTokenTimeBeforeRefresh(tokenPayload) > 0) {
          setTokenState({expTime, resCode: errorCode, refreshAttempts: refreshAttempts - 1})
        } else {
          clearToken()
          console.log('Refresh failed')
          setTokenState({expTime: false, resCode: errorCode, refreshAttempts: MAX_REFRESH_ATTEMPTS})
        }
      }
    }
    const scheduler = (timeout_ms) => setTimeout(() => tokenRefreshHandler(), timeout_ms)

    if (expTime === false) return

    // refresh immediately if app has just started or time before expiration less than refreshAttempts * 30 seconds
    if (expTime === 0) {
      if (refreshAttempts > 0) {
        if (refreshTimeout_ms(refreshAttempts) > 0) {
          setTokenState({...tokenState, expTime: 1})
        } else {
          timerId.current = scheduler(0)
        }
      } else {
        clearToken()
        setTokenState({expTime: false, resCode, refreshAttempts: MAX_REFRESH_ATTEMPTS})
      }
    } else if (refreshAttempts > 0) {
      timerId.current = scheduler(refreshTimeout_ms(refreshAttempts))
    } else {
      clearToken()
      setTokenState({expTime: false, resCode, refreshAttempts: MAX_REFRESH_ATTEMPTS})
    }

    return () => {
      clearTimeout(timerId.current)
    }
  }, [tokenState])

  // const setLoggedOutState = () => {
  //   if (timerId.current) clearTimeout(timerId.current)
  //   clearToken()
  //   setTokenState({expTime: false, resCode: 200, refreshAttempts: MAX_REFRESH_ATTEMPTS})
  // }

  return tokenState.expTime === 0 ? <div/> : (
    <Router>
      <PrivateRoute>
        <div/>
      </PrivateRoute>
    </Router>
    )
}


export default App