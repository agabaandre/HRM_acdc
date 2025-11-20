'use strict';

/** @type {import('sequelize-cli').Migration} */
module.exports = {
  async up(queryInterface, Sequelize) {
    // This migration ensures the finance_sessions table exists
    // (express-mysql-session creates it automatically, but this ensures it's tracked)
    const tableExists = await queryInterface.showAllTables().then(tables => 
      tables.includes('finance_sessions')
    );

    if (!tableExists) {
      await queryInterface.createTable('finance_sessions', {
        session_id: {
          type: Sequelize.STRING(128),
          primaryKey: true,
          allowNull: false
        },
        expires: {
          type: Sequelize.INTEGER.UNSIGNED,
          allowNull: false
        },
        data: {
          type: Sequelize.TEXT('medium'),
          allowNull: true
        }
      });

      // Add index on expires for faster cleanup queries
      await queryInterface.addIndex('finance_sessions', ['expires'], {
        name: 'idx_finance_sessions_expires'
      });
    }
  },

  async down(queryInterface, Sequelize) {
    await queryInterface.dropTable('finance_sessions');
  }
};

