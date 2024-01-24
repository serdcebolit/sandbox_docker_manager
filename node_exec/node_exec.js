const http = require("http");
const child_process = require("child_process");

const getUserId = (userName) => {
  return +(child_process.execSync(`id -u ${userName}`).toString());
};

const getGroupId = (userName) => {
  return +(child_process.execSync(`id -g ${userName}`).toString());
};

var server = http.createServer((req, res) => {
  if (req.method == 'POST') {
    req.on('data', (chunk) => {
      var data = JSON.parse(chunk.toString());
      if (data.command && data.command.length) {
        try {
          let options = {};
          if (data.options && data.options['sudo']) {
            options.uid = getUserId('root');
            options.gid = getGroupId('root');
          } else {
            options.uid = getUserId('bitrix');
            options.gid = getGroupId('bitrix');
          }

          child_process.exec(data.command, options, (error, stdout, stderr) => {
            var ret = {
              command: data.command,
              stdout: stdout,
              stderr: stderr,
            };
            console.log(ret);
            const response = JSON.stringify(ret);
            res.writeHead(200, [["Content-Type", "application/json"], ["Content-Length", response.length]]);
            res.write(response);
            res.end();
          });
        } catch (e) {
          console.error('Ошибка ' + e.name + ":" + e.message + "\n" + e.stack);
        }
      }
    });
  }
});
server.timeout = 30 * 60 * 1000;
/*server.setTimeout(30 * 60 * 1000, () => {
  console.error('Timeout error');
  let err = new Error('Service Unavailable');
  err.status = 503;
});*/
server.listen(8081, function () {
  console.log("Server start...")
});