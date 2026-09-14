# Sequelize ORM Setup Guide

## Overview

The Finance app now uses **Sequelize ORM** for database operations and migrations. Sequelize provides:
- ✅ Object-Relational Mapping (ORM)
- ✅ Database migrations
- ✅ Model associations
- ✅ Query building
- ✅ Transaction support
- ✅ Excellent MySQL support

## Installation

1. **Install dependencies**:
   ```bash
   cd finance
   npm install --legacy-peer-deps
   ```

2. **Verify installation**:
   ```bash
   npx sequelize-cli --version
   ```

## Configuration

The ORM is configured in:
- `server/config/database.js` - Database configuration for different environments
- `server/models/index.js` - Sequelize instance and model initialization
- `.sequelizerc` - Sequelize CLI configuration paths

## Creating Models

### Generate a Model with Migration

```bash
npm run model:generate -- --name User --attributes firstName:string,lastName:string,email:string
```

This creates:
- `server/models/User.js` - Model file
- `server/migrations/YYYYMMDDHHMMSS-create-user.js` - Migration file

### Manual Model Creation

Create a model file in `server/models/`:

```javascript
// server/models/Example.js
module.exports = (sequelize, DataTypes) => {
  const Example = sequelize.define('Example', {
    id: {
      type: DataTypes.INTEGER,
      primaryKey: true,
      autoIncrement: true
    },
    name: {
      type: DataTypes.STRING,
      allowNull: false
    },
    description: {
      type: DataTypes.TEXT,
      allowNull: true
    }
  }, {
    tableName: 'examples',
    timestamps: true
  });

  return Example;
};
```

Then register it in `server/models/index.js`:
```javascript
db.Example = require('./Example')(sequelize, Sequelize.DataTypes);
```

## Migrations

### Create a Migration

```bash
npm run migration:generate -- --name create-examples-table
```

This creates: `server/migrations/YYYYMMDDHHMMSS-create-examples-table.js`

### Migration Example

```javascript
'use strict';

module.exports = {
  async up(queryInterface, Sequelize) {
    await queryInterface.createTable('examples', {
      id: {
        allowNull: false,
        autoIncrement: true,
        primaryKey: true,
        type: Sequelize.INTEGER
      },
      name: {
        type: Sequelize.STRING,
        allowNull: false
      },
      description: {
        type: Sequelize.TEXT,
        allowNull: true
      },
      created_at: {
        allowNull: false,
        type: Sequelize.DATE,
        defaultValue: Sequelize.literal('CURRENT_TIMESTAMP')
      },
      updated_at: {
        allowNull: false,
        type: Sequelize.DATE,
        defaultValue: Sequelize.literal('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')
      }
    });
  },

  async down(queryInterface, Sequelize) {
    await queryInterface.dropTable('examples');
  }
};
```

### Run Migrations

```bash
# Run all pending migrations
npm run db:migrate

# Check migration status
npm run db:migrate:status

# Rollback last migration
npm run db:migrate:undo

# Rollback all migrations
npm run db:migrate:undo:all
```

## Using Models in Code

### Import Sequelize Instance

```javascript
const db = require('../models');

// Access models
const { Example } = db;
```

### Basic CRUD Operations

```javascript
// Create
const example = await Example.create({
  name: 'Test',
  description: 'Test description'
});

// Read
const examples = await Example.findAll();
const example = await Example.findByPk(1);
const example = await Example.findOne({ where: { name: 'Test' } });

// Update
await example.update({ name: 'Updated Name' });
// or
await Example.update(
  { name: 'Updated Name' },
  { where: { id: 1 } }
);

// Delete
await example.destroy();
// or
await Example.destroy({ where: { id: 1 } });
```

### Querying

```javascript
// Find with conditions
const examples = await Example.findAll({
  where: {
    name: 'Test',
    id: { [db.Sequelize.Op.gt]: 5 }
  },
  order: [['created_at', 'DESC']],
  limit: 10,
  offset: 0
});

// Count
const count = await Example.count({ where: { name: 'Test' } });

// Find or Create
const [example, created] = await Example.findOrCreate({
  where: { name: 'Test' },
  defaults: { description: 'Default description' }
});
```

### Associations

Define in `server/models/index.js`:

```javascript
// One-to-Many
db.User.hasMany(db.Finance);
db.Finance.belongsTo(db.User);

// Many-to-Many
db.User.belongsToMany(db.Role, { through: 'user_roles' });
db.Role.belongsToMany(db.User, { through: 'user_roles' });
```

Use associations:

```javascript
// Include related data
const user = await User.findByPk(1, {
  include: [{
    model: Finance,
    as: 'finances'
  }]
});

// Create with association
const user = await User.create({ name: 'John' });
await user.createFinance({ amount: 100 });
```

### Transactions

```javascript
const transaction = await db.sequelize.transaction();

try {
  const user = await User.create({ name: 'John' }, { transaction });
  await Finance.create({ userId: user.id, amount: 100 }, { transaction });
  
  await transaction.commit();
} catch (error) {
  await transaction.rollback();
  throw error;
}
```

## Seeders

### Create a Seeder

```bash
npx sequelize-cli seed:generate --name demo-examples
```

### Run Seeders

```bash
# Run all seeders
npm run db:seed

# Undo all seeders
npm run db:seed:undo
```

## Integration with Existing Code

You can use Sequelize alongside the existing database module:

```javascript
// Use Sequelize for complex queries
const db = require('./models');
const { Finance } = db;

// Use existing database module for simple queries
const dbSimple = require('./database');
const results = await dbSimple.query('SELECT * FROM users WHERE id = ?', [1]);
```

## Best Practices

1. **Always use migrations** for schema changes
2. **Never modify existing migrations** - create new ones instead
3. **Use transactions** for operations that must succeed or fail together
4. **Define associations** in `server/models/index.js`
5. **Use model validations** for data integrity
6. **Keep migrations reversible** (implement both `up` and `down`)

## Troubleshooting

### Migration fails
- Check database connection in `.env`
- Verify migration syntax
- Check if table already exists

### Model not found
- Ensure model is registered in `server/models/index.js`
- Check model file exports correctly

### Connection errors
- Verify database credentials in `.env`
- Check database server is running
- Verify network connectivity

## Next Steps

1. Create your first model and migration
2. Run migrations: `npm run db:migrate`
3. Start using models in your controllers
4. Set up associations as needed

