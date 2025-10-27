export default function ArticlesPage() {
  return (
    <div className="space-y-6">
      <div className="admin-card">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">
          Articles
        </h1>
        <p className="text-gray-600 dark:text-gray-400">
          Manage all your articles here.
        </p>
      </div>

      <div className="admin-card">
        <div className="alert alert-info">
          <strong>Coming Soon!</strong> The articles management interface will be available here.
        </div>
      </div>
    </div>
  );
}
