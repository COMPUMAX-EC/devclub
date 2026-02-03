export const Ziggy = {
  url: '', // puede quedar vacío; ziggy-js toma la actual
  port: null,
  defaults: {},
  routes: {
    'admin.home':        { uri: 'admin/dashboard', methods: ['GET','HEAD'] },
    'admin.users.index':      { uri: 'admin/users', methods: ['GET','HEAD'] },
    'admin.users.create':     { uri: 'admin/users/create', methods: ['GET','HEAD'] },
    'admin.users.store':      { uri: 'admin/users', methods: ['POST'] },
    'admin.users.show':       { uri: 'admin/users/{user}', methods: ['GET','HEAD'], parameters: ['user'] },
    'admin.users.edit':       { uri: 'admin/users/{user}/edit', methods: ['GET','HEAD'], parameters: ['user'] },
    'admin.users.update':     { uri: 'admin/users/{user}', methods: ['PUT','PATCH'], parameters: ['user'] },
    'admin.users.destroy':    { uri: 'admin/users/{user}', methods: ['DELETE'], parameters: ['user'] },
    'admin.logout':           { uri: 'admin/logout', methods: ['POST'] },
  },
}
