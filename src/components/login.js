// ============================================================================
// LOGIN SCREEN -- Phase 3.3 Authentication UI
// ============================================================================

/**
 * LoginScreen component.
 * Shows username/password login form, or demo user-switcher if demo mode is on.
 *
 * Props:
 *   onLogin(user) - called when login succeeds, with the user object
 *   demoMode - boolean, if true show user-switcher instead of login
 *   people - array of users (only used in demo mode)
 */
function LoginScreen({ onLogin, demoMode, people }) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const result = await api.login(username, password);
      if (result.user) {
        onLogin(result.user);
      }
    } catch (err) {
      setError(err.message || 'Invalid credentials');
    } finally {
      setLoading(false);
    }
  };

  const handleDemoUserSelect = async (userId) => {
    setError('');
    setLoading(true);

    try {
      const result = await api.demoLogin(userId);
      if (result.user) {
        onLogin(result.user);
      }
    } catch (err) {
      setError(err.message || 'Demo login failed');
    } finally {
      setLoading(false);
    }
  };

  // Demo mode: show user-switcher
  if (demoMode && people && people.length > 0) {
    // Group users by role type
    const agencyUsers = people.filter(p => p.role !== 'Client');
    const clientUsers = people.filter(p => p.role === 'Client');

    return React.createElement('div', { className: 'login-screen' },
      React.createElement('div', { className: 'login-card login-card-demo' },
        React.createElement('div', { className: 'login-header' },
          React.createElement('h1', { className: 'login-title' }, 'Slash 301 PM'),
          React.createElement('p', { className: 'login-subtitle' }, 'Demo Mode -- Select a user to continue')
        ),

        error && React.createElement('div', { className: 'login-error' }, error),

        React.createElement('div', { className: 'demo-user-grid' },
          React.createElement('h3', { className: 'demo-section-label' }, 'Agency Team'),
          React.createElement('div', { className: 'demo-user-list' },
            agencyUsers.map(user =>
              React.createElement('button', {
                key: user.id,
                className: 'demo-user-btn',
                onClick: () => handleDemoUserSelect(user.id),
                disabled: loading,
              },
                React.createElement('span', {
                  className: 'demo-user-avatar',
                  style: { backgroundColor: user.color || '#3b82f6' }
                }, user.name.split(' ').map(n => n[0]).join('')),
                React.createElement('span', { className: 'demo-user-info' },
                  React.createElement('span', { className: 'demo-user-name' }, user.name),
                  React.createElement('span', { className: 'demo-user-role' }, user.role)
                )
              )
            )
          ),

          clientUsers.length > 0 && React.createElement(React.Fragment, null,
            React.createElement('h3', { className: 'demo-section-label' }, 'Client Users'),
            React.createElement('div', { className: 'demo-user-list' },
              clientUsers.map(user =>
                React.createElement('button', {
                  key: user.id,
                  className: 'demo-user-btn',
                  onClick: () => handleDemoUserSelect(user.id),
                  disabled: loading,
                },
                  React.createElement('span', {
                    className: 'demo-user-avatar',
                    style: { backgroundColor: user.color || '#3b82f6' }
                  }, user.name.split(' ').map(n => n[0]).join('')),
                  React.createElement('span', { className: 'demo-user-info' },
                    React.createElement('span', { className: 'demo-user-name' }, user.name),
                    React.createElement('span', { className: 'demo-user-role' },
                      user.role + (user.brand ? ' - ' + user.brand : ''))
                  )
                )
              )
            )
          )
        )
      )
    );
  }

  // Normal login form
  return React.createElement('div', { className: 'login-screen' },
    React.createElement('div', { className: 'login-card' },
      React.createElement('div', { className: 'login-header' },
        React.createElement('h1', { className: 'login-title' }, 'Slash 301 PM'),
        React.createElement('p', { className: 'login-subtitle' }, 'Sign in to your account')
      ),

      error && React.createElement('div', { className: 'login-error' }, error),

      React.createElement('form', { className: 'login-form', onSubmit: handleSubmit },
        React.createElement('div', { className: 'login-field' },
          React.createElement('label', { htmlFor: 'login-username' }, 'Username'),
          React.createElement('input', {
            id: 'login-username',
            type: 'text',
            value: username,
            onChange: e => setUsername(e.target.value),
            placeholder: 'e.g. priya-nair-coo',
            autoComplete: 'username',
            autoFocus: true,
            disabled: loading,
            required: true,
          })
        ),
        React.createElement('div', { className: 'login-field' },
          React.createElement('label', { htmlFor: 'login-password' }, 'Password'),
          React.createElement('input', {
            id: 'login-password',
            type: 'password',
            value: password,
            onChange: e => setPassword(e.target.value),
            placeholder: 'Enter your password',
            autoComplete: 'current-password',
            disabled: loading,
            required: true,
          })
        ),
        React.createElement('button', {
          type: 'submit',
          className: 'login-submit',
          disabled: loading || !username || !password,
        }, loading ? 'Signing in...' : 'Sign In')
      )
    )
  );
}
