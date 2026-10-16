const { Sequelize } = require('sequelize');
const config = require('../config/database');

const env = process.env.NODE_ENV || 'development';
const dbConfig = config[env];

// Create Sequelize instance
const sequelize = new Sequelize(
  dbConfig.database,
  dbConfig.username,
  dbConfig.password,
  {
    host: dbConfig.host,
    port: dbConfig.port,
    dialect: dbConfig.dialect,
    charset: dbConfig.charset,
    logging: dbConfig.logging,
    pool: dbConfig.pool,
    define: {
      timestamps: true, // Enable createdAt and updatedAt
      underscored: false, // Use camelCase for column names
      freezeTableName: true // Don't pluralize table names
    }
  }
);

// Test connection
async function testConnection() {
  try {
    await sequelize.authenticate();
    console.log('Sequelize: Database connection established successfully.');
    return true;
  } catch (error) {
    console.error('Sequelize: Unable to connect to the database:', error);
    return false;
  }
}

// Initialize models
const db = {
  sequelize,
  Sequelize,
  testConnection
};

// Import models here as you create them
// Example:
// db.User = require('./User')(sequelize, Sequelize.DataTypes);
// db.Finance = require('./Finance')(sequelize, Sequelize.DataTypes);

// Define associations here
// Example:
// db.User.hasMany(db.Finance);
// db.Finance.belongsTo(db.User);

module.exports = db;

